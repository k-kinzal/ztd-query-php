<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Command\Admin\Literals;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;

/**
 * Checks source switch prerequisites against the existing channel configuration.
 *
 * RESET REPLICA preserves these settings; RESET REPLICA ALL removes them.
 * Observed through SQL on MySQL 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 *
 * @visibility MySqlMemory
 */
final class SourceSwitches
{
    /**
     * Requires GTIDs, row events and auto-positioning before enabling their dependent settings.
     *
     * @param array<string, SourceOption> $options The last supplied value of each setting
     */
    public function check(array $options, Session $session, string $channel): void
    {
        $state = $session->instance->registry->replication;
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
    public function apply(array $options, Session $session): void
    {
        $state = $session->instance->registry->replication;
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
