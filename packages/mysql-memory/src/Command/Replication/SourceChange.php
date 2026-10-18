<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Command\Account\Names;
use MySqlMemory\Command\Admin\Literals;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\AnonymousGtids;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;

/**
 * Checks replication source settings against the server's GTID mode, accounts and network timeout.
 *
 * Heartbeat notices are already resolved by SQL Semantics. Credential and
 * position warnings precede compression validation. Repeated options use their final value.
 * This class validates settings; transporting transactions between servers is not implemented.
 * Verified against MySQL 8.0.44 and 8.4.7 through SQL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 *
 * @visibility MySqlMemory
 */
final class SourceChange
{
    /**
     * Validates the effective settings and records warnings in server order.
     */
    public function check(ChangeReplicationSource $statement, Session $session, Context $context): void
    {
        $options = [];
        foreach ($statement->options as $option) {
            $options[$option->kind->value] = $option;
        }
        $channel = $statement->channel === null ? '' : strtolower((new \SqlSemantics\Platform\MySql\Rules\Replication\ChannelName())->value($statement->channel));
        $this->channel($channel, $session);
        $registry = $session->instance->registry;
        if ($statement->channel === null && $registry->channels !== []) {
            throw AdministrationError::ReplicaMultipleChannels->error();
        }
        $state = $channel === '' ? $registry->replication : ($registry->channels[$channel] ??= new \MySqlMemory\Registry\Replication());
        (new SourceRepository())->open($state);
        $this->gtids($options, $session);
        (new SourceSwitches())->check($options, $session, $channel);
        $state->initialized = true;
        $user = $options[SourceOptionKind::PrivilegeChecksUser->value]->value ?? null;
        if ($user instanceof AccountName) {
            $identity = (new Names())->identity($user, $session);
            if ($session->instance->accounts->find($identity) === null) {
                throw AdministrationError::ReplicationUserMissing->error($channel, $identity->user, $identity->host);
            }
        }
        $timeout = $session->variables->read($session->settings()->legacy() ? 'slave_net_timeout' : 'replica_net_timeout');
        (new SourceRepository())->update($options, $state, $channel, (float) $timeout / 2);
        $this->warnings($options, $session, $context);
        $compression = $options[SourceOptionKind::CompressionAlgorithms->value]->value ?? null;
        if ($compression instanceof Text) {
            $this->compression((new Literals())->bytes($compression), $channel);
        }
        (new SourceRepository())->check($state);
        $this->relay($options, $session, $channel);
        (new SourceSwitches())->apply($options, $session, $channel);
    }

    /**
     * Checks the relay filename against the index, retaining logs only for an explicit relay position.
     *
     * @param array<string, SourceOption> $options The final option of each kind
     */
    public function relay(array $options, Session $session, string $channel = ''): void
    {
        $replication = $channel === '' ? $session->instance->registry->replication : $session->instance->registry->channels[$channel];
        $file = $options[SourceOptionKind::RelayLogFile->value]->value ?? null;
        if ($file instanceof Text) {
            $base = basename((string) $session->variables->read('relay_log'));
            $replication->missing = basename((new Literals())->bytes($file)) !== $base . '.000001';
            if ($replication->missing) {
                throw AdministrationError::RelayLogPosition->error("Could not find target log file mentioned in applier metadata in the index file './" . $base . ".index' during relay log initialization");
            }
        }
        if ($file === null && !isset($options[SourceOptionKind::RelayLogPosition->value]) && !$replication->applying) {
            $replication->closed = false;
            $replication->missing = false;
        }
    }

    /**
     * Validates a new channel after heartbeat notices and before GTID or credential checks.
     * Named channels require TABLE repositories; names contain at most 64 characters.
     *
     * @throws \MySqlMemory\Error\SqlError When repository settings or the name prevent creation
     */
    public function channel(string $channel, Session $session): void
    {
        if ($channel !== '' && in_array('FILE', [$session->variables->read('master_info_repository'), $session->variables->read('relay_log_info_repository')], true)) {
            throw AdministrationError::ReplicaChannelRepository->error();
        }
        if (mb_strlen($channel, 'UTF-8') > 64) {
            throw AdministrationError::InvalidReplicaChannel->error();
        }
    }

    /**
     * Refuses auto-positioning with GTIDs off, and anonymous assignment unless they are on.
     *
     * @param array<string, SourceOption> $options The final option of each kind
     */
    public function gtids(array $options, Session $session): void
    {
        $mode = strtoupper((string) $session->variables->read('gtid_mode'));
        $position = $options[SourceOptionKind::AutoPosition->value]->value ?? null;
        if ($position instanceof Numeral && (new Literals())->number($position) !== '0' && $mode === 'OFF') {
            throw AdministrationError::AutoPositionWithoutGtid->error();
        }
        $assignment = $options[SourceOptionKind::AssignGtidsToAnonymousTransactions->value]->value ?? null;
        if ($assignment !== null && $assignment !== AnonymousGtids::Off && $mode !== 'ON') {
            throw AdministrationError::AnonymousAssignmentWithoutGtid->error();
        }
    }

    /**
     * Warns about an unpaired log filename and storing connection credentials.
     *
     * @param array<string, SourceOption> $options The final option of each kind
     */
    public function warnings(array $options, Session $session, Context $context): void
    {
        $assignment = $options[SourceOptionKind::AssignGtidsToAnonymousTransactions->value]->value ?? null;
        if ($assignment !== null && $assignment !== AnonymousGtids::Off) {
            $session->diagnostics->note(AdministrationError::AnonymousAssignmentTopology, AdministrationError::AnonymousAssignmentTopology->message());
        }
        if (isset($options[SourceOptionKind::LogFile->value]) && !isset($options[SourceOptionKind::LogPosition->value])) {
            $context->warning(AdministrationError::SourceFileWithoutPosition);
        }
        if (isset($options[SourceOptionKind::User->value]) || isset($options[SourceOptionKind::Password->value])) {
            $session->diagnostics->note(AccountError::InsecurePlainText, AccountError::InsecurePlainText->message());
            $session->diagnostics->note(AdministrationError::StoredReplicationCredentials, AdministrationError::StoredReplicationCredentials->message());
        }
    }

    /**
     * Refuses the first unknown compression algorithm; names are case insensitive and a final comma is accepted.
     *
     * @throws \MySqlMemory\Error\SqlError When any algorithm is unknown
     */
    public function compression(string $algorithms, string $channel): void
    {
        $names = explode(',', $algorithms);
        if (count($names) > 1 && $names[count($names) - 1] === '') {
            array_pop($names);
        }
        foreach ($names as $name) {
            if (!in_array(strtolower($name), ['zlib', 'zstd', 'uncompressed'], true)) {
                throw AdministrationError::ReplicationCompression->error($name, $channel);
            }
        }
    }
}
