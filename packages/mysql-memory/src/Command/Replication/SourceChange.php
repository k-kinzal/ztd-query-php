<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Command\Account\Names;
use MySqlMemory\Command\Admin\Literals;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
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
 * Heartbeat warnings precede validation of GTID and privilege-check settings. Credential and
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
        $channel = $statement->channel === null ? '' : strtolower((new Literals())->bytes($statement->channel));
        $heartbeat = $options[SourceOptionKind::HeartbeatPeriod->value]->value ?? null;
        if ($heartbeat instanceof NumberLiteral) {
            $this->heartbeat((float) $heartbeat->text, $session, $context);
        }
        $this->gtids($options, $session);
        $session->instance->registry->replication->initialized = true;
        $user = $options[SourceOptionKind::PrivilegeChecksUser->value]->value ?? null;
        if ($user instanceof AccountName) {
            $identity = (new Names())->identity($user, $session);
            if ($session->instance->accounts->find($identity) === null) {
                throw AdministrationError::ReplicationUserMissing->error($channel, $identity->user, $identity->host);
            }
        }
        $this->warnings($options, $session, $context);
        $compression = $options[SourceOptionKind::CompressionAlgorithms->value]->value ?? null;
        if ($compression instanceof Text) {
            $this->compression((new Literals())->bytes($compression), $channel);
        }
        $this->relay($options, $session);
    }

    /**
     * Checks the relay filename against the index, retaining logs only for an explicit relay position.
     *
     * @param array<string, SourceOption> $options The final option of each kind
     */
    public function relay(array $options, Session $session): void
    {
        $replication = $session->instance->registry->replication;
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
     * Warns about a positive sub-millisecond heartbeat or one exceeding the global timeout.
     */
    public function heartbeat(float $period, Session $session, Context $context): void
    {
        if ($period > 0 && $period < 0.001) {
            $context->warning(AdministrationError::HeartbeatBelowMinimum);
        }
        $legacy = in_array($session->settings()->release(), [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $name = $legacy ? 'slave_net_timeout' : 'replica_net_timeout';
        if ($period > (float) $session->variables->read($name)) {
            $context->warning(AdministrationError::HeartbeatAboveTimeout, $name);
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
