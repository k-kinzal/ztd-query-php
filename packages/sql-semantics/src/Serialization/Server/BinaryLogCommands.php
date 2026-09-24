<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Server;

use SqlSemantics\Model\Configuration\Administration\BinaryLogReset;
use SqlSemantics\Model\Configuration\Administration\ReplicaReset;
use SqlSemantics\Model\Configuration\Administration\ResetTarget;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Server\Administration;
use SqlSemantics\Model\Statement\Server\Replication;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes binary log purges and server state resets in the spelling of the release they were bound against.
 * @visibility SqlSemantics
 */
final class BinaryLogCommands
{
    /**
     * Writes the purge by log name or by cut-off time.
     */
    public static function purge(Replication\PurgeBinaryLogsToStatement|Replication\PurgeBinaryLogsBeforeStatement $statement): Tree
    {
        if ($statement instanceof Replication\PurgeBinaryLogsToStatement) {
            return new Tree('purge', [Build::keyword('PURGE BINARY LOGS TO'), Expressions::write($statement->logName)]);
        }
        return new Tree('purge', [Build::keyword('PURGE BINARY LOGS BEFORE'), Expressions::write($statement->moment)]);
    }

    /**
     * Writes the reset options in the spelling of the release: MASTER and SLAVE before 8.0, REPLICA from 8.0, and BINARY LOGS AND GTIDS from 8.2.
     */
    public static function reset(Administration\ResetServerStatement $statement): Tree
    {
        $release = ReplicationRelease::of($statement->origin) ?? PHP_INT_MAX;
        return new Tree('reset', [Build::keyword('RESET'), Build::separated(array_map(static fn (ResetTarget $target): Tree => self::target($target, $release), $statement->targets))]);
    }

    /**
     * Writes one reset option for the release.
     */
    public static function target(ResetTarget $target, int $release): Tree
    {
        if ($target instanceof ReplicaReset) {
            return new Tree('reset-replica', [Build::keyword($release < 80000 ? 'SLAVE' : 'REPLICA'), ...($target->all ? [Build::keyword('ALL')] : []), ...ReplicationCommands::channel($target->channel)]);
        }
        if ($target instanceof BinaryLogReset) {
            return new Tree('reset-binary-logs', [Build::keyword($release < 80200 ? 'MASTER' : 'BINARY LOGS AND GTIDS'), ...($target->firstIndex === null ? [] : [Build::keyword('TO'), Expressions::write($target->firstIndex)])]);
        }
        return Build::keyword('QUERY CACHE');
    }
}
