<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Replication;

use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaThread;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;

/**
 * The checks the server's parser applies to the UNTIL clause and the connection options of START REPLICA.
 *
 * Rule: MYSQL-REPLICA-UNTIL-001. The UNTIL condition is refused
 * (ER_BAD_REPLICA_UNTIL_COND) when a source or relay log position is
 * combined with a GTID set, when it names neither a complete source log
 * position (file and position), a complete relay log position, a GTID set
 * nor SQL_AFTER_MTS_GAPS, or when SQL_AFTER_MTS_GAPS is combined with any
 * other condition. Connection options (USER, PASSWORD, DEFAULT_AUTH,
 * PLUGIN_DIR) are refused when the thread list starts the applier thread
 * without the receiver thread (ER_SQLTHREAD_WITH_SECURE_REPLICA). The same
 * checks apply in every release. Terminates: one pass over the lists.
 * Source: sql/sql_yacc.yy (opt_replica_until, slave_until,
 * start_replica_stmt, slave), https://dev.mysql.com/doc/refman/8.4/en/start-replica.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ReplicaConditions
{
    /**
     * Answers the check an UNTIL clause fails, if any.
     */
    public function until(ReplicaUntil $until): ?ReplicationError
    {
        $written = [];
        foreach ($until->positions as $position) {
            $written[$position->kind->value] = true;
        }
        $source = isset($written[SourceOptionKind::LogFile->value]) || isset($written[SourceOptionKind::LogPosition->value]);
        $relay = isset($written[SourceOptionKind::RelayLogFile->value]) || isset($written[SourceOptionKind::RelayLogPosition->value]);
        $gtids = $until->gtids !== null;
        $gaps = $until->point === UntilPoint::AfterGaps;
        $complete = (isset($written[SourceOptionKind::LogFile->value]) && isset($written[SourceOptionKind::LogPosition->value]))
            || (isset($written[SourceOptionKind::RelayLogFile->value]) && isset($written[SourceOptionKind::RelayLogPosition->value]));
        $refused = (($source || $relay) && $gtids) || !($complete || $gtids || $gaps) || (($source || $relay || $gtids) && $gaps);

        return $refused ? ReplicationError::UntilCondition : null;
    }

    /**
     * Tells whether connection options are refused for the threads a START REPLICA names.
     *
     * @param list<ReplicaThread> $threads
     */
    public function credentialsRefused(array $threads, bool $credentials): bool
    {
        return $credentials && in_array(ReplicaThread::Applier, $threads, true) && !in_array(ReplicaThread::Receiver, $threads, true);
    }
}
