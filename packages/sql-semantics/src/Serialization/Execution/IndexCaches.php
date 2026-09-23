<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Execution;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\IndexCache as Cache;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Maintenance\MySql as Statement;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes cache assignments and preloads from their distinct native request forms.
 * @visibility SqlSemantics
 */
final class IndexCaches
{
    /**
     * Writes only the index-cache command family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        $targets = match (true) {
            $statement instanceof Statement\CacheTableIndexesStatement => Build::separated(array_map(self::target(...), $statement->targets)),
            $statement instanceof Statement\CachePartitionIndexesStatement => self::target($statement->target, $statement->partitions),
            $statement instanceof Statement\PreloadTableIndexesStatement => Build::separated(array_map(static fn (Cache\PreloadTarget $target): Tree => self::preload($target), $statement->targets)),
            $statement instanceof Statement\PreloadPartitionIndexesStatement => self::preload($statement->target, $statement->partitions),
            default => null,
        };
        if ($targets === null) {
            return null;
        }
        if ($statement instanceof Statement\CacheTableIndexesStatement || $statement instanceof Statement\CachePartitionIndexesStatement) {
            return new Tree('cache_indexes', [Build::keyword('CACHE INDEX'), $targets, Build::keyword('IN'), $statement->cache instanceof Cache\CacheName ? Build::identifier([$statement->cache->name], Dialect::MySql) : Build::keyword('DEFAULT')]);
        }
        return new Tree('preload_indexes', [Build::keyword('LOAD INDEX INTO CACHE'), $targets]);
    }

    /**
     * Writes a physical target and its explicit selection requests.
     */
    public static function target(Cache\TableIndexes $target, Cache\AllPartitions|Cache\NamedPartitions|null $partitions = null): Tree
    {
        $parts = [Relations::target($target->table, Dialect::MySql)];
        if ($partitions !== null) {
            $parts[] = Build::keyword('PARTITION');
            $parts[] = Build::parentheses($partitions instanceof Cache\NamedPartitions ? self::names($partitions->names) : Build::keyword('ALL'));
        }
        if ($target->indexes !== null) {
            $parts[] = Build::keyword('INDEX');
            $parts[] = Build::parentheses(self::names($target->indexes->names));
        }
        return new Tree('cache_target', $parts);
    }

    /**
     * Keeps the IGNORE LEAVES policy with its owning table.
     */
    public static function preload(Cache\PreloadTarget $target, Cache\AllPartitions|Cache\NamedPartitions|null $partitions = null): Tree
    {
        return new Tree('preload_target', [self::target($target->indexes, $partitions), Build::keyword($target->ignoreLeaves ? 'IGNORE LEAVES' : '')]);
    }

    /**
     * @param list<string> $names Unqualified identifier spellings
     */
    public static function names(array $names): Tree
    {
        return Build::separated(array_map(static fn (string $name): Tree => Build::identifier([$name], Dialect::MySql), $names));
    }
}
