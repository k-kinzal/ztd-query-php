<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Server;

use SqlSemantics\Model\Configuration\Administration\FlushTarget;
use SqlSemantics\Model\Configuration\Administration\RelayLogFlush;
use SqlSemantics\Model\Configuration\Administration\ServerFlush;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Server\Administration;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes FLUSH requests from their tables, options and binary log policy.
 * @visibility SqlSemantics
 */
final class FlushCommands
{
    /**
     * Writes a table form or an option list.
     */
    public static function write(Administration\FlushTablesStatement|Administration\FlushTablesWithReadLockStatement|Administration\FlushTablesForExportStatement|Administration\FlushServerStatement $statement): Tree
    {
        $prefix = [Build::keyword('FLUSH'), ...($statement->binlog === BinlogPolicy::Omit ? [Build::keyword('NO_WRITE_TO_BINLOG')] : [])];
        if ($statement instanceof Administration\FlushServerStatement) {
            return new Tree('flush', [...$prefix, Build::separated(array_map(self::target(...), $statement->targets))]);
        }
        $tables = $statement->tables === [] ? [] : [Build::separated(array_map(static fn (TableReference $table): Tree => Relations::target($table, $statement->origin->dialect), $statement->tables))];
        $lock = match (true) {
            $statement instanceof Administration\FlushTablesWithReadLockStatement => [Build::keyword('WITH READ LOCK')],
            $statement instanceof Administration\FlushTablesForExportStatement => [Build::keyword('FOR EXPORT')],
            $statement instanceof Administration\FlushTablesStatement => [],
        };
        return new Tree('flush', [...$prefix, Build::keyword('TABLES'), ...$tables, ...$lock]);
    }

    /**
     * Writes one flush option with its channel.
     */
    public static function target(FlushTarget $target): Tree
    {
        if ($target instanceof ServerFlush) {
            return Build::keyword($target->value);
        }
        if ($target instanceof RelayLogFlush) {
            return new Tree('relay-logs', [Build::keyword('RELAY LOGS'), ...ReplicationCommands::channel($target->channel)]);
        }
        return Build::keyword('LOGS');
    }
}
