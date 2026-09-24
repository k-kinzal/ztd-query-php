<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\MySqlTable;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange;
use SqlSemantics\Model\Definition\MySqlTable\Table;
use SqlSemantics\Model\Maintenance\IndexCache\AllPartitions;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Ordering;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Definition\Storage;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes MySQL table-level alterations and partition commands from their typed operands.
 * @visibility SqlSemantics
 */
final class TableChanges
{
    /**
     * Writes a table-level alteration, or returns null for another alteration.
     */
    public static function write(object $alteration): ?Tree
    {
        return match (true) {
            $alteration instanceof Table\TableCommand => Build::keyword($alteration->value),
            $alteration instanceof Table\RenameTable => new Tree('rename-table', [Build::keyword('RENAME TO'), Build::identifier($alteration->newName->parts, Dialect::MySql)]),
            $alteration instanceof Table\ConvertCharacterSet => new Tree('convert', [Build::keyword('CONVERT TO CHARACTER SET'), is_string($alteration->characterSet) ? ColumnChanges::name($alteration->characterSet) : Build::keyword('DEFAULT'), ...($alteration->collation === null ? [] : [Build::keyword('COLLATE'), ColumnChanges::name($alteration->collation)])]),
            $alteration instanceof Table\ChangeTableOptions => new Tree('table-options', [Storage::mysql($alteration->options, Dialect::MySql), TableOptionWriter::resets($alteration->resets)]),
            $alteration instanceof Table\OrderRows => new Tree('order-rows', [Build::keyword('ORDER BY'), Build::separated(array_map(static fn (Ordering $ordering): Tree => new Tree('ordering', [Expressions::write($ordering->key instanceof \SqlSemantics\Model\Expression ? $ordering->key : throw new \SqlSemantics\Model\Validation\InvalidStructure('A row order names columns.')), Build::keyword($ordering->descending ? 'DESC' : '')]), $alteration->orderings))]),
            $alteration instanceof Table\RepartitionTable => Partitionings::write($alteration->partitioning),
            default => self::partitions($alteration),
        };
    }

    /**
     * Writes a standalone partition command, or returns null for another alteration.
     */
    public static function partitions(object $alteration): ?Tree
    {
        return match (true) {
            $alteration instanceof PartitionChange\AddPartitions => new Tree('add-partitions', [self::head('ADD', $alteration->binlog), Partitionings::definitions($alteration->partitions)]),
            $alteration instanceof PartitionChange\AddPartitionCount => new Tree('add-partitions', [self::head('ADD', $alteration->binlog), Build::keyword('PARTITIONS ' . $alteration->count)]),
            $alteration instanceof PartitionChange\DropPartitions => new Tree('drop-partitions', [Build::keyword('DROP PARTITION'), self::selection(new NamedPartitions($alteration->partitions))]),
            $alteration instanceof PartitionChange\ProcessPartitions => new Tree('process-partitions', [self::head($alteration->process->value, $alteration->binlog), self::selection($alteration->partitions)]),
            $alteration instanceof PartitionChange\CheckPartitions => new Tree('check-partitions', [Build::keyword('CHECK PARTITION'), self::selection($alteration->partitions), ...array_map(static fn ($option): Tree => Build::keyword($option->value), $alteration->options)]),
            $alteration instanceof PartitionChange\RepairPartitions => new Tree('repair-partitions', [self::head('REPAIR', $alteration->binlog), self::selection($alteration->partitions), ...array_map(static fn ($option): Tree => Build::keyword($option->value), $alteration->options)]),
            $alteration instanceof PartitionChange\TruncatePartitions => new Tree('truncate-partitions', [Build::keyword('TRUNCATE PARTITION'), self::selection($alteration->partitions)]),
            $alteration instanceof PartitionChange\CoalescePartitions => new Tree('coalesce-partitions', [self::head('COALESCE', $alteration->binlog), Build::keyword((string) $alteration->count)]),
            $alteration instanceof PartitionChange\ReorganizePartitions => new Tree('reorganize-partitions', [self::head('REORGANIZE', $alteration->binlog), self::selection(new NamedPartitions($alteration->partitions)), Build::keyword('INTO'), Partitionings::definitions($alteration->into)]),
            $alteration instanceof PartitionChange\RebuildPartitioning => self::head('REORGANIZE', $alteration->binlog),
            $alteration instanceof PartitionChange\ExchangePartition => new Tree('exchange-partition', [Build::keyword('EXCHANGE PARTITION'), ColumnChanges::name($alteration->partition), Build::keyword('WITH TABLE'), Relations::target($alteration->table, Dialect::MySql)]),
            $alteration instanceof PartitionChange\PartitionTablespaces => new Tree('partition-tablespaces', [Build::keyword($alteration->action->value . ' PARTITION'), self::selection($alteration->partitions), Build::keyword('TABLESPACE')]),
            $alteration instanceof PartitionChange\SecondaryLoad => new Tree('secondary-load', [Build::keyword($alteration->action->value), ...($alteration->partitions === [] ? [] : [Build::keyword('PARTITION'), Build::parentheses(self::selection(new NamedPartitions($alteration->partitions)))])]),
            default => null,
        };
    }

    /**
     * Writes a command keyword followed by PARTITION and the binary logging policy.
     */
    public static function head(string $command, BinlogPolicy $binlog): Tree
    {
        return Build::keyword(trim($command . ' PARTITION ' . $binlog->value));
    }

    /**
     * Writes ALL or a list of partition names.
     */
    public static function selection(AllPartitions|NamedPartitions $partitions): Tree
    {
        return $partitions instanceof AllPartitions ? Build::keyword($partitions->value) : Build::separated(array_map(ColumnChanges::name(...), $partitions->names));
    }
}
