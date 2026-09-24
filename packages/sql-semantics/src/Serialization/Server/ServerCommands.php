<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Server;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Server\Administration;
use SqlSemantics\Model\Statement\Server\Replication;

/**
 * Routes MySQL replication, binary log, flush, clone, component and instance commands to their writers.
 * @visibility SqlSemantics
 */
final class ServerCommands
{
    /**
     * Returns null for statements outside this family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Replication\PurgeBinaryLogsToStatement,
            $statement instanceof Replication\PurgeBinaryLogsBeforeStatement => BinaryLogCommands::purge($statement),
            $statement instanceof Administration\InstallComponentStatement,
            $statement instanceof Administration\UninstallComponentStatement => AdministrationCommands::components($statement),
            $statement instanceof Administration\RotateMasterKeyStatement,
            $statement instanceof Administration\ReloadTlsStatement,
            $statement instanceof Administration\ReloadKeyringStatement,
            $statement instanceof Administration\AlterRedoLogStatement => AdministrationCommands::instance($statement),
            $statement instanceof Administration\CloneRemoteStatement => AdministrationCommands::clone($statement),
            $statement instanceof Replication\ChangeReplicationSourceStatement => ChangeCommands::source($statement),
            $statement instanceof Replication\ChangeReplicationFilterStatement => ChangeCommands::filter($statement),
            $statement instanceof Administration\FlushTablesStatement,
            $statement instanceof Administration\FlushTablesWithReadLockStatement,
            $statement instanceof Administration\FlushTablesForExportStatement,
            $statement instanceof Administration\FlushServerStatement => FlushCommands::write($statement),
            $statement instanceof Administration\ResetServerStatement => BinaryLogCommands::reset($statement),
            $statement instanceof Replication\StartReplicaStatement,
            $statement instanceof Replication\StopReplicaStatement => ReplicationCommands::replica($statement),
            $statement instanceof Replication\StartGroupReplicationStatement,
            $statement instanceof Replication\StopGroupReplicationStatement => ReplicationCommands::group($statement),
            default => null,
        };
    }
}
