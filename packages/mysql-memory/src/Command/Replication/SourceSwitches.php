<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Command\Admin\Literals;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;

/**
 * Checks source switch prerequisites against the existing channel configuration.
 *
 * RESET REPLICA preserves these settings; RESET REPLICA ALL removes them.
 * Observed through SQL on MySQL 5.6.51, 5.7.44, 8.0.44 and 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 *
 * @visibility MySqlMemory
 */
final class SourceSwitches
{
    /**
     * Refuses applier settings while the channel's SQL thread runs.
     *
     * @param array<string, SourceOption> $options The final option of each kind
     */
    public function threads(array $options, \MySqlMemory\Registry\Replication $state, string $channel, GrammarRelease $release): void
    {
        if (!$state->applying) {
            return;
        }
        if ($release === GrammarRelease::MySql5651) {
            throw AdministrationError::LegacyReplicaRunning->error();
        }
        foreach ([
            [AdministrationError::ReplicaChannelRunning, [SourceOptionKind::AutoPosition, SourceOptionKind::GtidOnly, SourceOptionKind::AssignGtidsToAnonymousTransactions]],
            [AdministrationError::ReplicaApplierRunning, [SourceOptionKind::RelayLogFile, SourceOptionKind::RelayLogPosition, SourceOptionKind::Delay, SourceOptionKind::PrivilegeChecksUser, SourceOptionKind::RequireRowFormat, SourceOptionKind::RequireTablePrimaryKeyCheck]],
        ] as [$code, $kinds]) {
            foreach ($kinds as $kind) {
                if (isset($options[$kind->value])) {
                    $error = $code->error($channel);
                    throw $release === GrammarRelease::MySql5744 ? new SqlError($code, str_replace(['replica sql', 'REPLICA'], ['slave sql', 'SLAVE'], $error->getMessage())) : $error;
                }
            }
        }
    }

    /**
     * Requires GTIDs, row events and auto-positioning before enabling their dependent settings.
     *
     * @param array<string, SourceOption> $options The last supplied value of each setting
     */
    public function check(array $options, Session $session, string $channel): void
    {
        $state = $channel === '' ? $session->instance->registry->replication : $session->instance->registry->channels[$channel];
        $mode = strtoupper((string) $session->variables->read('gtid_mode'));
        $position = $this->value($options, SourceOptionKind::AutoPosition, $state->autoPosition);
        $row = $this->value($options, SourceOptionKind::RequireRowFormat, $state->requireRowFormat);
        $only = $this->value($options, SourceOptionKind::GtidOnly, $state->gtidOnly);
        if ($only && isset($options[SourceOptionKind::GtidOnly->value]) && ($mode !== 'ON' || !$position || !$row)) {
            throw AdministrationError::GtidOnlyRequirements->error($channel);
        }
        if ($only && !$row) {
            throw AdministrationError::GtidOnlyRowFormat->error($channel);
        }
        if ($only && !$position) {
            throw AdministrationError::GtidOnlyAutoPosition->error($channel);
        }
        if ($this->value($options, SourceOptionKind::ConnectionAutoFailover, $state->autoFailover)) {
            if ($mode !== 'ON') {
                throw AdministrationError::FailoverWithoutGtid->error();
            }
            if (!$position) {
                throw AdministrationError::FailoverWithoutAutoPosition->error();
            }
        }
    }

    /**
     * Retains successfully applied source switches for later statements.
     *
     * @param array<string, SourceOption> $options The last supplied value of each setting
     */
    public function apply(array $options, Session $session, string $channel = ''): void
    {
        $state = $channel === '' ? $session->instance->registry->replication : $session->instance->registry->channels[$channel];
        $state->autoPosition = $this->value($options, SourceOptionKind::AutoPosition, $state->autoPosition);
        $state->requireRowFormat = $this->value($options, SourceOptionKind::RequireRowFormat, $state->requireRowFormat);
        $state->gtidOnly = $this->value($options, SourceOptionKind::GtidOnly, $state->gtidOnly);
        $state->autoFailover = $this->value($options, SourceOptionKind::ConnectionAutoFailover, $state->autoFailover);
    }

    /**
     * Reads a supplied numeric switch or preserves its current channel value.
     *
     * @param array<string, SourceOption> $options The last supplied value of each setting
     */
    public function value(array $options, SourceOptionKind $kind, bool $current): bool
    {
        $value = $options[$kind->value]->value ?? null;

        return $value instanceof Numeral ? (new Literals())->number($value) !== '0' : $current;
    }
}
