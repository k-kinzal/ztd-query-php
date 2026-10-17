<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Command\Admin\Literals;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Registry\Replication;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;

/**
 * Retains connection settings independently of whether the metadata repository accepts them.
 *
 * The port is unsigned modulo 2^32; zero preserves the old port. TABLE metadata
 * stores only signed nonnegative ports. A rejected value survives until a later
 * nonzero assignment or RESET REPLICA ALL. Observed through SQL on 8.0.44,
 * 8.4.7 and 9.1.0, including errors competing with compression and relay settings.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 *
 * @visibility MySqlMemory
 */
final class SourceRepository
{
    /**
     * Retains connection parameters before compression and repository validation.
     *
     * @param array<string, SourceOption> $options The final setting of each kind
     */
    public function update(array $options, Replication $state, string $channel, float $defaultHeartbeat): void
    {
        $host = $options[SourceOptionKind::Host->value]->value ?? null;
        if ($host instanceof \SqlSemantics\Platform\MySql\Statement\Literal\Text) {
            $state->host = explode("\0", $host->bytes(), 2)[0];
        }
        $heartbeat = $options[SourceOptionKind::HeartbeatPeriod->value]->value ?? null;
        $state->heartbeatPeriods[$channel] ??= min($defaultHeartbeat, 4294967.0);
        if ($heartbeat instanceof \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral) {
            $period = (float) $heartbeat->text;
            $state->heartbeatPeriods[$channel] = $period < 0.001 ? 0.0 : $period;
        }
        $value = $options[SourceOptionKind::Port->value]->value ?? null;
        if ($value instanceof Numeral) {
            $number = (new Literals())->number($value);
            $number = bccomp($number, '18446744073709551615') > 0 ? '18446744073709551615' : $number;
            $port = (int) bcmod($number, '4294967296');
            if ($port !== 0) {
                $state->port = $port;
            }
        }
    }

    /**
     * Reports the repository error and the command-specific following conditions.
     *
     * @throws SqlError When the connection port cannot be stored in the TABLE repository
     */
    public function check(Replication $state, bool $reset = false): void
    {
        if ($state->port <= 2147483647) {
            return;
        }
        $state->connectionFailure = $state->connectionFailure || $reset;
        $error = AdministrationError::ReplicationDataTooLong;
        $following = $state->connectionFailure
            ? [[AdministrationError::ReplicaConnectionInitialization->value, AdministrationError::ReplicaConnectionInitialization->message()]]
            : [[AdministrationError::RelayLogPosition->value, AdministrationError::RelayLogPosition->message('Failed to flush connection metadata repository')]];

        if ($reset) {
            $following[] = [StatementError::UnknownError->value, StatementError::UnknownError->message()];
        }

        throw new SqlError($error, $error->message('Port'), following: $following);
    }
    /**
     * Retries connection metadata initialization before reading any new settings.
     */
    public function open(Replication $state): void
    {
        if ($state->connectionFailure) {
            $this->check($state);
        }
    }

}
