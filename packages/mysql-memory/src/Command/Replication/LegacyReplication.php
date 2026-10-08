<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Error\AdministrationError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetQueryCache;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents;
use SqlSemantics\Statement\Node;

/**
 * Refuses the replica statements of MySQL 5.6 and 5.7 on a server without a server id, and warns that RESET QUERY CACHE is deprecated in 5.7.
 *
 * Those releases start with server_id 0, which leaves the replica uninitialized: START SLAVE,
 * STOP SLAVE, RESET SLAVE, CHANGE MASTER, CHANGE REPLICATION FILTER and SHOW RELAYLOG EVENTS fail
 * with ER_SLAVE_CONFIGURATION in the terminology of the release (verified on live 5.6.51 and
 * 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/5.7/en/replication-options.html#sysvar_server_id.
 *
 * @visibility MySqlMemory
 */
final class LegacyReplication
{
    /**
     * The message of ER_SLAVE_CONFIGURATION in 5.6 and 5.7.
     */
    public const UNCONFIGURED = 'Slave is not configured or failed to initialize properly. You must at least set --server-id to enable either a master or a slave. Additional error messages can be found in the MySQL error log.';

    /**
     * Raises the error of a replica statement on a 5.6 or 5.7 server without a server id, and records the warning of RESET QUERY CACHE in 5.7.
     *
     * @throws SqlError When the statement needs the replica
     */
    public function check(Node $statement, Session $session): void
    {
        $release = $session->settings()->release();
        if ($release !== GrammarRelease::MySql5651 && $release !== GrammarRelease::MySql5744) {
            return;
        }
        $replica = $statement instanceof StartReplica || $statement instanceof StopReplica || $statement instanceof ChangeReplicationSource || $statement instanceof ChangeReplicationFilter || $statement instanceof ShowRelaylogEvents;
        foreach ($statement instanceof Reset ? $statement->targets : [] as $target) {
            $replica = $replica || $target instanceof ResetReplica;
            if ($target instanceof ResetQueryCache && $release === GrammarRelease::MySql5744) {
                $session->diagnostics->warning(1681, "'RESET QUERY CACHE' is deprecated and will be removed in a future release.");
            }
        }
        if ($replica && (int) $session->variables->read('server_id') === 0) {
            throw new SqlError(AdministrationError::ReplicaNotInitialized, self::UNCONFIGURED);
        }
    }
}
