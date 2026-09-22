<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Session;

use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Configuration\DiscardStatement;
use SqlSemantics\Model\Statement\Configuration\SetAllConstraintsStatement;
use SqlSemantics\Model\Statement\Notification\ListenStatement;
use SqlSemantics\Model\Statement\Notification\NotifyStatement;
use SqlSemantics\Model\Statement\Notification\UnlistenAllStatement;
use SqlSemantics\Model\Statement\Notification\UnlistenStatement;
use SqlSemantics\Model\Statement\Server\ApplyBinlogStatement;
use SqlSemantics\Model\Statement\Server\CheckpointStatement;
use SqlSemantics\Model\Statement\Server\CloneLocalStatement;
use SqlSemantics\Model\Statement\Server\InstallPluginStatement;
use SqlSemantics\Model\Statement\Server\KillConnectionStatement;
use SqlSemantics\Model\Statement\Server\KillQueryStatement;
use SqlSemantics\Model\Statement\Server\RestartServerStatement;
use SqlSemantics\Model\Statement\Server\ShutdownServerStatement;
use SqlSemantics\Model\Statement\Server\UninstallPluginStatement;
use SqlSemantics\Model\Statement\Server\UnlockTablesStatement;
use SqlSemantics\Serialization\Expressions;

/**
 * Serializes explicit session and server operations from their semantic operands.
 * @visibility SqlSemantics
 */
final class SessionStatements
{
    /**
     * Writes the operation without executing it or resolving runtime values.
     */
    public static function write(CheckpointStatement|RestartServerStatement|ShutdownServerStatement|UnlockTablesStatement|ListenStatement|UnlistenStatement|UnlistenAllStatement|NotifyStatement|KillConnectionStatement|KillQueryStatement|InstallPluginStatement|UninstallPluginStatement|CloneLocalStatement|ApplyBinlogStatement|DiscardStatement|SetAllConstraintsStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        return match (true) {
            $statement instanceof CheckpointStatement => Build::keyword('CHECKPOINT'),
            $statement instanceof RestartServerStatement => Build::keyword('RESTART'),
            $statement instanceof ShutdownServerStatement => Build::keyword('SHUTDOWN'),
            $statement instanceof UnlockTablesStatement => Build::keyword('UNLOCK TABLES'),
            $statement instanceof ListenStatement => new Tree('listen', [Build::keyword('LISTEN'), Build::identifier([$statement->channel], $dialect)]),
            $statement instanceof UnlistenStatement => new Tree('unlisten', [Build::keyword('UNLISTEN'), Build::identifier([$statement->channel], $dialect)]),
            $statement instanceof UnlistenAllStatement => Build::keyword('UNLISTEN *'),
            $statement instanceof NotifyStatement => new Tree('notify', [Build::keyword('NOTIFY'), Build::identifier([$statement->channel], $dialect), ...($statement->payload === null ? [] : [Build::keyword(','), Expressions::write($statement->payload)])]),
            $statement instanceof KillConnectionStatement => new Tree('kill-connection', [Build::keyword('KILL CONNECTION'), Expressions::write($statement->connectionId)]),
            $statement instanceof KillQueryStatement => new Tree('kill-query', [Build::keyword('KILL QUERY'), Expressions::write($statement->connectionId)]),
            $statement instanceof InstallPluginStatement => new Tree('install-plugin', [Build::keyword('INSTALL PLUGIN'), Build::identifier([$statement->name], $dialect), Build::keyword('SONAME'), Expressions::write($statement->library)]),
            $statement instanceof UninstallPluginStatement => new Tree('uninstall-plugin', [Build::keyword('UNINSTALL PLUGIN'), Build::identifier([$statement->name], $dialect)]),
            $statement instanceof CloneLocalStatement => new Tree('clone-local', [Build::keyword('CLONE LOCAL DATA DIRECTORY'), Expressions::write($statement->directory)]),
            $statement instanceof ApplyBinlogStatement => new Tree('binlog', [Build::keyword('BINLOG'), Expressions::write($statement->encodedEvent)]),
            $statement instanceof DiscardStatement => new Tree('discard', [Build::keyword('DISCARD'), Build::keyword($statement->resource->value)]),
            $statement instanceof SetAllConstraintsStatement => new Tree('constraints', [Build::keyword('SET CONSTRAINTS ALL'), Build::keyword($statement->timing->value)]),
        };
    }
}
