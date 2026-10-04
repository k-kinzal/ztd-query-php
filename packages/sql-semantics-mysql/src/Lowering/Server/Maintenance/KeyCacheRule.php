<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Maintenance;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionSelection;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\PrimaryIndex;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CachedTable;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\LoadIndex;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\PreloadedTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * Lowers CACHE INDEX and LOAD INDEX INTO CACHE.
 *
 * Rule: MYSQL-KEY-CACHE-001. Scope: keycache, keycache_list_or_parts,
 * assign_to_keycache_parts, preload, preload_list_or_parts,
 * preload_keys_parts, cache_keys_spec, cache_key_list_or_empty (5.6, 5.7),
 * keycache_stmt, preload_stmt, opt_cache_key_list (8.0 and later),
 * keycache_list, assign_to_keycache, key_cache_name, preload_list,
 * preload_keys, adm_partition, opt_ignore_leaves. The index list goes
 * through the query family's index rule, which reads PRIMARY as the primary
 * key; the partitions through the table change family's partition list
 * rule. KEY and INDEX are synonyms (LeafNoise). Constructs: CacheIndex,
 * CachedTable, LoadIndex, PreloadedTable. Terminates: lists are flattened
 * iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cache-index.html,
 * https://dev.mysql.com/doc/refman/8.4/en/load-index.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
 */
final class KeyCacheRule
{
    /**
     * The productions of one entry: the position of the table, of the partitions, of the index list and of IGNORE LEAVES.
     */
    private const ENTRIES = [
        'assign_to_keycache: table_ident cache_keys_spec' => [0, null, 1, null],
        'assign_to_keycache: table_ident opt_cache_key_list' => [0, null, 1, null],
        'assign_to_keycache_parts: table_ident adm_partition cache_keys_spec' => [0, 1, 2, null],
        'preload_keys: table_ident cache_keys_spec opt_ignore_leaves' => [0, null, 1, 2],
        'preload_keys: table_ident opt_cache_key_list opt_ignore_leaves' => [0, null, 1, 2],
        'preload_keys_parts: table_ident adm_partition cache_keys_spec opt_ignore_leaves' => [0, 1, 2, 3],
        'keycache_stmt: CACHE_SYM INDEX_SYM table_ident adm_partition opt_cache_key_list IN_SYM key_cache_name' => [2, 3, 4, null],
        'preload_stmt: LOAD INDEX_SYM INTO CACHE_SYM table_ident adm_partition opt_cache_key_list opt_ignore_leaves' => [4, 5, 6, 7],
    ];

    /**
     * The list productions: the entries are the items of the list, or the one child of a unit production.
     */
    private const LISTS = [
        'keycache_list_or_parts: keycache_list', 'keycache_list_or_parts: assign_to_keycache_parts', 'keycache_list: assign_to_keycache',
        'keycache_list: keycache_list , assign_to_keycache', 'preload_list_or_parts: preload_keys_parts', 'preload_list_or_parts: preload_list',
        'preload_list: preload_keys', 'preload_list: preload_list , preload_keys',
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a key cache statement.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        return match ($form->signature) {
            'keycache: CACHE_SYM INDEX_SYM keycache_list_or_parts IN_SYM key_cache_name', 'keycache_stmt: CACHE_SYM INDEX_SYM keycache_list IN_SYM key_cache_name' => new CacheIndex(
                $this->cached($this->entries($form->node(2))),
                $this->cache($form->node(4)),
            ),
            'keycache_stmt: CACHE_SYM INDEX_SYM table_ident adm_partition opt_cache_key_list IN_SYM key_cache_name' => new CacheIndex($this->cached([$form]), $this->cache($form->node(6))),
            'preload: LOAD INDEX_SYM INTO CACHE_SYM preload_list_or_parts', 'preload_stmt: LOAD INDEX_SYM INTO CACHE_SYM preload_list' => new LoadIndex($this->preloaded($this->entries($form->node(4)))),
            'preload_stmt: LOAD INDEX_SYM INTO CACHE_SYM table_ident adm_partition opt_cache_key_list opt_ignore_leaves' => new LoadIndex($this->preloaded([$form])),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Answers the entry productions of an entry list, in written order.
     *
     * @return list<Form>
     */
    public function entries(Node $list): array
    {
        $entries = [];
        $pending = [$list];
        while ($pending !== []) {
            $form = $this->lowering->form(array_shift($pending));
            if (!in_array($form->signature, self::LISTS, true)) {
                $entries[] = $form;
                continue;
            }
            $children = count($form->node->children) === 1 ? [$form->node(0)] : (new Lists())->items($form->node);
            array_unshift($pending, ...$children);
        }

        return $entries;
    }

    /**
     * Lowers the entries of CACHE INDEX.
     *
     * @param list<Form> $entries
     * @return list<CachedTable>
     * @throws ImplementationGap When a production has no rule
     */
    public function cached(array $entries): array
    {
        $tables = [];
        foreach ($entries as $entry) {
            [$table, $partitions, $indexes] = $this->entry($entry);
            $tables[] = new CachedTable($table, $partitions, $indexes);
        }

        return $tables;
    }

    /**
     * Lowers the entries of LOAD INDEX INTO CACHE.
     *
     * @param list<Form> $entries
     * @return list<PreloadedTable>
     * @throws ImplementationGap When a production has no rule
     */
    public function preloaded(array $entries): array
    {
        $tables = [];
        foreach ($entries as $entry) {
            [$table, $partitions, $indexes, $ignoreLeaves] = $this->entry($entry);
            $tables[] = new PreloadedTable($table, $partitions, $indexes, $ignoreLeaves);
        }

        return $tables;
    }

    /**
     * Lowers the table, the partitions, the index list and IGNORE LEAVES of one entry.
     *
     * @return array{\SqlSemantics\Statement\Identifier\QualifiedName, PartitionSelection|null, list<Name|PrimaryIndex>|null, bool}
     * @throws ImplementationGap When a production has no rule
     */
    public function entry(Form $form): array
    {
        [$table, $partitions, $indexes, $leaves] = self::ENTRIES[$form->signature] ?? throw ImplementationGap::production($form);

        return [
            $this->lowering->names->qualified($form->node($table)),
            $partitions === null ? null : $this->partitions($form->node($partitions)),
            $this->indexes($form->node($indexes)),
            $leaves !== null && $this->leaves($form->node($leaves)),
        ];
    }

    /**
     * Lowers a partition selection: a node of `adm_partition`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function partitions(Node $partition): PartitionSelection
    {
        $form = $this->lowering->form($partition);

        return match ($form->signature) {
            'adm_partition: PARTITION_SYM have_partitioning ( all_or_alt_part_name_list )' => $this->marked($form),
            'adm_partition: PARTITION_SYM ( all_or_alt_part_name_list )' => $this->lowering->tableChanges->partitionNames($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the 5.x partition selection that holds the have_partitioning marker.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function marked(Form $form): PartitionSelection
    {
        $this->lowering->options->skip($form->node(1));

        return $this->lowering->tableChanges->partitionNames($form->node(3));
    }

    /**
     * Lowers an optional index list: a node of `cache_keys_spec` or `opt_cache_key_list`; an absent list is null.
     *
     * @return list<Name|PrimaryIndex>|null
     * @throws ImplementationGap When a production has no rule
     */
    public function indexes(Node $list): ?array
    {
        $form = $this->lowering->form($list);
        if ($form->signature === 'cache_keys_spec: cache_key_list_or_empty') {
            $form = $this->lowering->form($form->node(0));
        }

        return match ($form->signature) {
            'cache_key_list_or_empty:', 'opt_cache_key_list:' => null,
            'cache_key_list_or_empty: key_or_index ( opt_key_usage_list )', 'opt_cache_key_list: key_or_index ( opt_key_usage_list )' => $this->keys($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a written index list.
     *
     * @return list<Name|PrimaryIndex>
     * @throws ImplementationGap When a production has no rule
     */
    public function keys(Form $form): array
    {
        $this->lowering->options->skip($form->node(0));

        return $this->lowering->queries->indexKeys($form->node(2));
    }

    /**
     * Lowers IGNORE LEAVES: a node of `opt_ignore_leaves`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function leaves(Node $leaves): bool
    {
        $form = $this->lowering->form($leaves);

        return match ($form->signature) {
            'opt_ignore_leaves:' => false,
            'opt_ignore_leaves: IGNORE_SYM LEAVES' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the key cache name: a node of `key_cache_name`; DEFAULT is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function cache(Node $name): ?Name
    {
        $form = $this->lowering->form($name);

        return match ($form->signature) {
            'key_cache_name: ident' => $this->lowering->names->identifier($form->node(0)),
            'key_cache_name: DEFAULT', 'key_cache_name: DEFAULT_SYM' => null,
            default => throw ImplementationGap::production($form),
        };
    }
}
