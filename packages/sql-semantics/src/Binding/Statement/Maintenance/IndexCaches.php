<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Maintenance;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\IndexCache as Cache;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Maintenance\MySql as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;

/**
 * Resolves index-cache requests without allocating caches or loading index pages.
 * @visibility SqlSemantics
 */
final class IndexCaches
{
    /**
     * Separates whole-table lists from a single table's partition selection.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::MySql || !in_array($node->name, ['keycache', 'keycache_stmt', 'preload', 'preload_stmt'], true)) {
            return null;
        }
        $preload = in_array($node->name, ['preload', 'preload_stmt'], true);
        $partitions = Tree::outer($node, ['adm_partition'])[0] ?? null;
        $cache = Tree::outer($node, ['key_cache_name'])[0] ?? null;
        $name = $cache === null || strtoupper(Tree::text($cache)) === 'DEFAULT' ? Cache\DefaultCache::Instance : new Cache\CacheName($context->tables->identifiers->name($cache->tokens()[0]));
        if ($partitions !== null) {
            $target = self::target($node, $origin, $context);
            $selection = self::partitions($partitions, $context);
            return $preload
                ? new Statement\PreloadPartitionIndexesStatement($origin, new Cache\PreloadTarget($target, self::ignoreLeaves($node)), $selection)
                : new Statement\CachePartitionIndexesStatement($origin, $target, $selection, $name);
        }
        $nodes = Tree::outer($node, [$preload ? 'preload_keys' : 'assign_to_keycache']);
        if ($nodes === []) {
            throw new UnclassifiedSql('An index-cache operation requires table requests.');
        }
        $targets = array_map(static fn (Node $item): Cache\TableIndexes => self::target($item, $origin, $context), $nodes);
        if (!$preload) {
            return new Statement\CacheTableIndexesStatement($origin, Collections::nonEmpty($targets), $name);
        }
        $requests = [];
        foreach ($targets as $index => $target) {
            $requests[] = new Cache\PreloadTarget($target, self::ignoreLeaves($nodes[$index]));
        }
        return new Statement\PreloadTableIndexesStatement($origin, Collections::nonEmpty($requests));
    }

    /**
     * Retains index identifiers separately from the owning physical table.
     * @throws UnclassifiedSql
     */
    public static function target(Node $node, Origin $origin, QueryContext $context): Cache\TableIndexes
    {
        $name = Tree::outer($node, ['table_ident'])[0] ?? throw new UnclassifiedSql('An index-cache target requires a physical table.');
        $table = TableOccurrence::resolve($name, $context, $origin->scopeId);
        if (!$table instanceof TableReference) {
            throw new UnclassifiedSql('An index-cache target must be a physical table.');
        }
        $selection = Tree::outer($node, ['opt_cache_key_list', 'cache_key_list_or_empty'])[0] ?? null;
        $indexes = $selection === null || !Tree::hasTokens($selection) ? null : new Cache\NamedIndexes(array_map(static fn (Node $key): string => $context->tables->identifiers->name($key->tokens()[0]), Tree::outer($selection, ['key_usage_element'])));
        return new Cache\TableIndexes($table, $indexes);
    }

    /**
     * Reads ALL as a selection policy and named partitions as ordered identifiers.
     * @throws UnclassifiedSql
     */
    public static function partitions(Node $node, QueryContext $context): Cache\AllPartitions|Cache\NamedPartitions
    {
        $selection = Tree::outer($node, ['all_or_alt_part_name_list'])[0] ?? throw new UnclassifiedSql('A partition cache request requires its selection.');
        if (strtoupper(Tree::text($selection)) === 'ALL') {
            return Cache\AllPartitions::All;
        }
        return new Cache\NamedPartitions(Collections::nonEmpty(array_map(static fn (Node $name): string => $context->tables->identifiers->name($name->tokens()[0]), Tree::outer($selection, ['ident']))));
    }

    /**
     * Reads the leaf-page policy owned by one table request.
     */
    public static function ignoreLeaves(Node $node): bool
    {
        $option = Tree::outer($node, ['opt_ignore_leaves'])[0] ?? null;
        return $option !== null && Tree::hasTokens($option);
    }
}
