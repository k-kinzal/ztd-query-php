<?php

declare(strict_types=1);

namespace MySqlMemory\Hint;

use MySqlMemory\Error\Family\StatementError;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\BlockNameHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\ExecutionTimeHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\ResourceGroupHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\StrategyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\TableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintForm;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;

/**
 * Reads the hints of the query blocks of a statement as the server does before it resolves any name: which hints count, with the warnings about the others.
 *
 * The blocks are read in the order Blocks::$contextualized gives, each hint in written order.
 * QB_NAME names its block once, with a name no other block has (the `select#N` names included,
 * compared without regard to case). A hint that names a block (`@qb`, `t@qb`) refers to a block
 * named before it; INDEX and NO_INDEX, and the blocks the tables of a join order hint name, are
 * looked up after every hint is read. A hint that repeats or contradicts one read before is
 * ignored (ER_WARN_CONFLICTING_HINT): a table-level or index-level hint of the same kind for the
 * same object or one that contains it, JOIN_PREFIX and JOIN_SUFFIX once per block and no join
 * order hint with JOIN_FIXED_ORDER, one subquery hint per block, one MAX_EXECUTION_TIME and one
 * SET_VAR per variable; INDEX, JOIN_INDEX, GROUP_INDEX and ORDER_INDEX of the same table repeat
 * one another when their indexes overlap. INDEX_MERGE names no index or at least two.
 * MAX_EXECUTION_TIME applies in the first block of a SELECT statement only, RESOURCE_GROUP in
 * block 1 (the last one counts), neither in a stored procedure, and SET_VAR sets a known
 * variable that takes the hint, from any block (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html.
 *
 * @visibility MySqlMemory\Hint
 */
final class Registration
{
    /**
     * The system variables SET_VAR sets: those whose description says the hint applies (verified on live 8.0, 8.4 and 9.1 servers).
     */
    public const HINTABLE = [
        'auto_increment_increment', 'auto_increment_offset', 'big_tables', 'bulk_insert_buffer_size', 'cte_max_recursion_depth', 'default_table_encryption',
        'default_tmp_storage_engine', 'div_precision_increment', 'end_markers_in_json', 'eq_range_index_dive_limit', 'foreign_key_checks', 'group_concat_max_len',
        'insert_id', 'internal_tmp_mem_storage_engine', 'join_buffer_size', 'lock_wait_timeout', 'max_error_count', 'max_execution_time', 'max_heap_table_size',
        'max_join_size', 'max_length_for_sort_data', 'max_points_in_geometry', 'max_seeks_for_key', 'max_sort_length', 'optimizer_max_subgraph_pairs',
        'optimizer_prune_level', 'optimizer_search_depth', 'optimizer_switch', 'range_alloc_block_size', 'range_optimizer_max_mem_size', 'read_buffer_size',
        'read_rnd_buffer_size', 'secondary_engine_cost_threshold', 'select_into_buffer_size', 'select_into_disk_sync', 'select_into_disk_sync_delay',
        'set_operations_buffer_size', 'sort_buffer_size', 'sql_auto_is_null', 'sql_big_selects', 'sql_buffer_result', 'sql_mode', 'sql_require_primary_key',
        'sql_safe_updates', 'sql_select_limit', 'time_zone', 'timestamp', 'tmp_table_size', 'unique_checks', 'updatable_views_with_limit', 'use_secondary_engine',
        'windowing_use_high_precision', 'xa_detach_on_prepare',
    ];

    /**
     * The index-level hints read for one index at a time.
     */
    public const SPLIT = ['MRR', 'NO_ICP', 'NO_RANGE_OPTIMIZATION'];

    /**
     * The index-level hints INDEX repeats when their indexes overlap.
     */
    public const FAMILY = ['INDEX', 'JOIN_INDEX', 'GROUP_INDEX', 'ORDER_INDEX'];

    /**
     * @var list<array{int, string}> The warnings, each its error number and message, in order
     */
    public array $warnings = [];

    /**
     * @var array<string, int> The blocks by lower-case name
     */
    public array $names = [];

    /**
     * @var list<array{int, OptimizerHint, HintTable|null, string|null}> The hints that count for tables and indexes: the block they apply to, the hint, the table and the index
     */
    public array $accepted = [];

    /**
     * @var array<string, true> The objects a table-level or simple index-level hint applies to, by kind
     */
    public array $taken = [];

    /**
     * @var array<int|string, array<string, list<array{string, list<string>}>>> The index hints that name all their indexes, by block (or the name of a block INDEX writes) and table: their kind and indexes
     */
    public array $keys = [];

    /**
     * @var array<int, array<string, true>> The join order hints of each block, by kind
     */
    public array $orders = [];

    /**
     * @var array<int, true> The blocks that have a subquery hint
     */
    public array $subqueries = [];

    /**
     * The MAX_EXECUTION_TIME that counts, or null.
     */
    public ?ExecutionTimeHint $time = null;

    /**
     * The RESOURCE_GROUP that counts, or null.
     */
    public ?ResourceGroupHint $group = null;

    /**
     * @var array<string, VariableHint> The SET_VAR hints that count, by the name of their variable, in written order
     */
    public array $variables = [];

    /**
     * @param Blocks $blocks The query blocks of the statement
     * @param SystemVariables $catalog The system variables of the release
     * @param Printer $printer How hints are written in the warnings
     * @param bool $routine Whether the statement is one of a stored procedure, where MAX_EXECUTION_TIME and RESOURCE_GROUP do not apply
     */
    public function __construct(public readonly Blocks $blocks, public readonly SystemVariables $catalog, public readonly Printer $printer, public readonly bool $routine = false)
    {
    }

    /**
     * Reads every hint of the statement.
     */
    public function register(): self
    {
        foreach ($this->blocks->blocks as $number => $block) {
            $this->names['select#' . $number] = $number;
        }
        $deferred = [];
        foreach ($this->blocks->contextualized as $number) {
            $block = $this->blocks->blocks[$number];
            foreach ($block->hints as $hint) {
                $deferred = [...$deferred, ...$this->hint($block, $hint)];
            }
        }
        foreach ($deferred as [$block, $hint, $name]) {
            $target = $this->target($block, $hint instanceof KeyHint ? $hint->block ?? $hint->table->block : $hint->block, $name);
            if ($target !== null && $hint instanceof KeyHint) {
                $this->accepted[] = [$target, $hint, $hint->table, null];
            }
        }

        return $this;
    }

    /**
     * Reads one hint of a block; answers what names a block looked up later: the tables of a join order hint, or INDEX and NO_INDEX, with the hint.
     *
     * @return list<array{QueryBlock, HintTable|KeyHint, HintName}>
     */
    public function hint(QueryBlock $block, OptimizerHint $hint): array
    {
        if ($hint instanceof BlockNameHint) {
            $this->name($block, $hint);
        } elseif ($hint instanceof TableHint && $hint->hint->form() === HintForm::Table) {
            $this->level($block, $hint);
        } elseif ($hint instanceof TableHint) {
            return $this->order($block, $hint);
        } elseif ($hint instanceof KeyHint) {
            return $this->key($block, $hint);
        } elseif ($hint instanceof StrategyHint) {
            $target = $this->target($block, $hint->block, $hint->hint);
            if ($target !== null && isset($this->subqueries[$target])) {
                $this->conflict($this->printer->strategy($hint));
            } elseif ($target !== null) {
                $this->subqueries[$target] = true;
            }
        } elseif ($hint instanceof ExecutionTimeHint) {
            if (!$block->top || $this->routine) {
                $this->warn(StatementError::HintTimeMisplaced);
            } elseif ($this->time !== null) {
                $this->conflict($this->printer->time($hint));
            } else {
                $this->time = $hint;
            }
        } elseif ($hint instanceof ResourceGroupHint) {
            if ($block->number !== 1 || $this->routine) {
                $this->warn(StatementError::HintNotSupported, 'Subquery or Stored procedure or Trigger');
            } else {
                $this->group = $hint;
            }
        } elseif ($hint instanceof VariableHint) {
            $this->variable($hint);
        }

        return [];
    }

    /**
     * Reads QB_NAME: the block takes the name unless it has one or another block has it.
     */
    public function name(QueryBlock $block, BlockNameHint $hint): void
    {
        $key = mb_strtolower($hint->block);
        if ($block->name !== null || isset($this->names[$key])) {
            $this->conflict($this->printer->block($hint));

            return;
        }
        $this->names[$key] = $block->number;
        $block->name = $hint->block;
    }

    /**
     * Reads a table-level hint: for its block when it names no table, else for each table up to one whose block is unknown.
     */
    public function level(QueryBlock $block, TableHint $hint): void
    {
        $target = $this->target($block, $hint->block, $hint->hint);
        if ($target === null) {
            return;
        }
        $kind = self::kind($hint->hint);
        if ($hint->tables === []) {
            $this->take('block|' . $target . '|' . $kind, $this->printer->level($hint, null));

            return;
        }
        foreach ($hint->tables as $table) {
            $own = $table->block === null ? $target : $this->target($block, $table->block, $hint->hint);
            if ($own === null) {
                break;
            }
            if (isset($this->taken['block|' . $own . '|' . $kind])) {
                $this->conflict($this->printer->level($hint, $table));
            } elseif ($this->take('table|' . $own . '|' . $table->name . '|' . $kind, $this->printer->level($hint, $table))) {
                $this->accepted[] = [$own, $hint, $table, null];
            }
        }
    }

    /**
     * Reads a join order hint or JOIN_FIXED_ORDER; answers its tables that name a block, looked up later.
     *
     * @return list<array{QueryBlock, HintTable, HintName}>
     */
    public function order(QueryBlock $block, TableHint $hint): array
    {
        $target = $this->target($block, $hint->block, $hint->hint);
        if ($target === null) {
            return [];
        }
        $orders = $this->orders[$target] ?? [];
        $fixed = $hint->hint === HintName::JoinFixedOrder;
        if (isset($orders['JOIN_FIXED_ORDER']) || ($fixed && $orders !== []) || ($hint->hint !== HintName::JoinOrder && isset($orders[$hint->hint->value]))) {
            $this->conflict($this->printer->order($hint));

            return [];
        }
        $this->orders[$target][$hint->hint->value] = true;
        $later = [];
        foreach ($hint->tables as $table) {
            if ($table->block === null) {
                $this->accepted[] = [$target, $hint, $table, null];
            } else {
                $later[] = [$block, $table, $hint->hint];
            }
        }

        return $later;
    }

    /**
     * Reads an index-level hint: MRR, NO_MRR, NO_ICP and NO_RANGE_OPTIMIZATION for each index, the others for the table with all their indexes.
     *
     * INDEX and NO_INDEX are checked against the hints before them under the name of the block
     * they write, and the block is looked up after every hint is read: answers the hint then.
     *
     * @return list<array{QueryBlock, KeyHint, HintName}>
     */
    public function key(QueryBlock $block, KeyHint $hint): array
    {
        $kind = self::kind($hint->hint);
        if ($hint->hint === HintName::IndexMerge && count($hint->indexes) === 1) {
            $this->warn(StatementError::HintArgumentCount, $this->printer->key($hint));

            return [];
        }
        $named = $hint->block ?? $hint->table->block;
        $later = $kind === 'INDEX' && $named !== null;
        $target = $later ? 'name ' . mb_strtolower($named) : $this->target($block, $named, $hint->hint);
        if ($target === null) {
            return [];
        }
        if (in_array($kind, self::SPLIT, true)) {
            $this->split($hint, (int) $target, 'key|' . $target . '|' . $hint->table->name . '|' . $kind);

            return [];
        }
        $indexes = array_map(mb_strtolower(...), $hint->indexes);
        if ($this->repeats($target, $hint->table->name, $kind, $indexes)) {
            $this->conflict($this->printer->key($hint));

            return [];
        }
        $this->keys[$target][$hint->table->name][] = [$kind, $indexes];
        if ($later) {
            return [[$block, $hint, $hint->hint]];
        }
        $this->accepted[] = [(int) $target, $hint, $hint->table, null];

        return [];
    }

    /**
     * Reads MRR, NO_MRR, NO_ICP or NO_RANGE_OPTIMIZATION: for the table when it names no index, else for each index unless a hint took the table before.
     *
     * @param int $target The block the hint applies to
     * @param string $table The key of the table for the kind of the hint, which each index extends
     */
    public function split(KeyHint $hint, int $target, string $table): void
    {
        if ($hint->indexes === [] && $this->take($table, $this->printer->index($hint, null))) {
            $this->accepted[] = [$target, $hint, $hint->table, null];
        }
        foreach ($hint->indexes as $index) {
            if (isset($this->taken[$table])) {
                $this->conflict($this->printer->index($hint, $index));
            } elseif ($this->take($table . '|' . mb_strtolower($index), $this->printer->index($hint, $index))) {
                $this->accepted[] = [$target, $hint, $hint->table, $index];
            }
        }
    }

    /**
     * Tells whether an index-level hint that names all its indexes repeats one read before for the same table: one of the same kind, or INDEX against JOIN_INDEX, GROUP_INDEX or ORDER_INDEX when their indexes overlap (all indexes when none is named).
     *
     * @param int|string $target The block the hint applies to, or the name of the block INDEX writes
     * @param list<string> $indexes The lower-case indexes the hint names
     */
    public function repeats(int|string $target, string $table, string $kind, array $indexes): bool
    {
        foreach ($this->keys[$target][$table] ?? [] as [$other, $written]) {
            $family = in_array($kind, self::FAMILY, true) && in_array($other, self::FAMILY, true) && ($kind === 'INDEX') !== ($other === 'INDEX');
            if ($other === $kind || ($family && ($written === [] || $indexes === [] || array_intersect($written, $indexes) !== []))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reads SET_VAR: a variable the server knows and that takes the hint, once.
     */
    public function variable(VariableHint $hint): void
    {
        $definition = $this->catalog->find($hint->variable);
        if ($definition === null) {
            $this->warn(StatementError::HintUnresolvedName, "'" . $hint->variable . "'", 'SET_VAR');
        } elseif (!in_array($definition->name, self::HINTABLE, true)) {
            $this->warn(StatementError::HintVariableRefused, $definition->name);
        } elseif (isset($this->variables[$definition->name])) {
            $this->conflict($this->printer->variable($hint, $definition->name));
        } else {
            $this->variables[$definition->name] = $hint;
        }
    }

    /**
     * Answers the block a hint applies to: its own, or the one it names; warns when no block has the name.
     */
    public function target(QueryBlock $block, ?string $name, HintName $hint): ?int
    {
        if ($name === null) {
            return $block->number;
        }
        $number = $this->names[mb_strtolower($name)] ?? null;
        if ($number === null) {
            $this->warn(StatementError::HintBlockNotFound, $this->printer->quote($name), $hint->value);
        }

        return $number;
    }

    /**
     * Takes an object for a hint unless a hint took it before, which ignores this one; tells whether it was taken.
     */
    public function take(string $key, string $text): bool
    {
        if (isset($this->taken[$key])) {
            $this->conflict($text);

            return false;
        }
        $this->taken[$key] = true;

        return true;
    }

    /**
     * Warns that a hint is ignored as conflicting or duplicated.
     */
    public function conflict(string $text): void
    {
        $this->warn(StatementError::HintConflicting, $text);
    }

    /**
     * Records a warning.
     */
    public function warn(StatementError $error, string ...$arguments): void
    {
        $this->warnings[] = [$error->number(), $error->message(...$arguments)];
    }

    /**
     * Answers the kind of a hint that its NO_ form shares, such as BKA for BKA and NO_BKA, and MRR for MRR and NO_MRR.
     */
    public static function kind(HintName $hint): string
    {
        return in_array($hint, [HintName::NoIcp, HintName::NoRangeOptimization], true) ? $hint->value : (string) preg_replace('/\ANO_/', '', $hint->value);
    }
}
