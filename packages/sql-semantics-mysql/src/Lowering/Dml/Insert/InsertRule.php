<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml\Insert;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Dml\ValueRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertPriority;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\RowAlias;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Query;

/**
 * Lowers INSERT and REPLACE of every release.
 *
 * Rule: MYSQL-INSERT-LOWERING-001. Scope: insert, replace, insert2,
 * insert_table, table_name_with_opt_use_partition (5.6); insert_stmt,
 * replace_stmt, opt_INTO (5.7 and later); insert_lock_option,
 * replace_lock_option, opt_low_priority, opt_insert_update,
 * opt_insert_update_list, opt_values_reference. The head becomes
 * InsertInto; the source decides the statement class: rows of VALUES
 * (InsertRows), SET assignments (InsertSet) or a query (InsertQuery). INTO
 * is optional and has no meaning (DmlNoise). Terminates: the parts are
 * strict subtrees. Source: https://dev.mysql.com/doc/refman/8.4/en/insert.html,
 * https://dev.mysql.com/doc/refman/8.4/en/replace.html,
 * https://dev.mysql.com/doc/refman/5.6/en/insert.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Dml
 */
final class InsertRule
{
    /**
     * The positions of the parts of the 5.7 and later statements: REPLACE, lock option, IGNORE, table,
     * partitions, source, row alias, ON DUPLICATE KEY UPDATE.
     */
    private const LAYOUTS = [
        'insert_stmt: INSERT insert_lock_option opt_ignore opt_INTO table_ident opt_use_partition insert_from_constructor opt_insert_update_list' => [false, 1, 2, 4, 5, 6, null, 7],
        'insert_stmt: INSERT insert_lock_option opt_ignore opt_INTO table_ident opt_use_partition SET update_list opt_insert_update_list' => [false, 1, 2, 4, 5, 6, null, 8],
        'insert_stmt: INSERT insert_lock_option opt_ignore opt_INTO table_ident opt_use_partition insert_from_subquery opt_insert_update_list' => [false, 1, 2, 4, 5, 6, null, 7],
        'insert_stmt: INSERT_SYM insert_lock_option opt_ignore opt_INTO table_ident opt_use_partition insert_from_constructor opt_values_reference opt_insert_update_list' => [false, 1, 2, 4, 5, 6, 7, 8],
        'insert_stmt: INSERT_SYM insert_lock_option opt_ignore opt_INTO table_ident opt_use_partition SET_SYM update_list opt_values_reference opt_insert_update_list' => [false, 1, 2, 4, 5, 6, 8, 9],
        'insert_stmt: INSERT_SYM insert_lock_option opt_ignore opt_INTO table_ident opt_use_partition insert_query_expression opt_insert_update_list' => [false, 1, 2, 4, 5, 6, null, 7],
        'replace_stmt: REPLACE replace_lock_option opt_INTO table_ident opt_use_partition insert_from_constructor' => [true, 1, null, 3, 4, 5, null, null],
        'replace_stmt: REPLACE replace_lock_option opt_INTO table_ident opt_use_partition SET update_list' => [true, 1, null, 3, 4, 5, null, null],
        'replace_stmt: REPLACE replace_lock_option opt_INTO table_ident opt_use_partition insert_from_subquery' => [true, 1, null, 3, 4, 5, null, null],
        'replace_stmt: REPLACE_SYM replace_lock_option opt_INTO table_ident opt_use_partition insert_from_constructor' => [true, 1, null, 3, 4, 5, null, null],
        'replace_stmt: REPLACE_SYM replace_lock_option opt_INTO table_ident opt_use_partition SET_SYM update_list' => [true, 1, null, 3, 4, 5, null, null],
        'replace_stmt: REPLACE_SYM replace_lock_option opt_INTO table_ident opt_use_partition insert_query_expression' => [true, 1, null, 3, 4, 5, null, null],
    ];

    /**
     * The lock options by production.
     */
    private const PRIORITIES = [
        'insert_lock_option:' => null, 'insert_lock_option: LOW_PRIORITY' => InsertPriority::Low, 'insert_lock_option: DELAYED_SYM' => InsertPriority::Delayed,
        'insert_lock_option: HIGH_PRIORITY' => InsertPriority::High, 'replace_lock_option: DELAYED_SYM' => InsertPriority::Delayed,
        'opt_low_priority:' => null, 'opt_low_priority: LOW_PRIORITY' => InsertPriority::Low,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of `insert_stmt` or `replace_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        $layout = self::LAYOUTS[$form->signature] ?? throw ImplementationGap::production($form);
        [$replace, $lock, $ignore, $table, $partitions, $source] = $layout;
        $into = $this->lowering->form($form->node($table - 1));
        if ($into->signature !== 'opt_INTO:' && $into->signature !== 'opt_INTO: INTO') {
            throw ImplementationGap::production($into);
        }
        $target = new WriteTarget($this->lowering->names->qualified($form->node($table)), null, $this->lowering->queries->partitions($form->node($partitions)));
        $priority = $this->priority($form->node($lock));
        $skip = $ignore === null ? false : $this->lowering->options->present($form->node($ignore));
        $alias = $layout[6] === null ? null : $this->alias($form->node($layout[6]));
        $clause = $layout[7] === null ? null : $form->node($layout[7]);
        $hints = $this->lowering->hints($form->token(0));
        if ($form->node->children[$source] instanceof Node) {
            [$columns, $body] = (new InsertSourceRule($this->lowering))->source($form->node($source));
            $updates = $clause === null ? [] : (is_array($body) ? (new RetainedUpdates($this->lowering))->read($clause) : $this->updates($clause));

            return $this->build(new InsertInto($replace, $priority, $skip, $target, $columns, $hints), $body, $alias, $updates, (new \SqlSemantics\Construction\SourceRanges())->span($form->node)[1] ?? 0);
        }

        $updates = $clause === null ? [] : (new RetainedUpdates($this->lowering))->read($clause);
        return $this->set(new InsertInto($replace, $priority, $skip, $target, hints: $hints), $form->node($source + 1), $alias, $updates, (new \SqlSemantics\Construction\SourceRanges())->span($form->node)[1] ?? 0);
    }

    /**
     * Lowers a node of `insert` or `replace` (5.6).
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function legacy(Form $form): Statement
    {
        $replace = $form->signature === 'replace: REPLACE replace_lock_option insert2 insert_field_spec';
        if (!$replace && $form->signature !== 'insert: INSERT insert_lock_option opt_ignore insert2 insert_field_spec opt_insert_update') {
            throw ImplementationGap::production($form);
        }
        $hints = $this->lowering->hints($form->token(0));
        $offset = $replace ? 0 : 1;
        $target = $this->target($form->node(2 + $offset));
        $priority = $this->priority($form->node(1));
        $skip = !$replace && $this->lowering->options->present($form->node(2));
        $clause = $replace ? null : $form->node(5);
        $spec = $this->lowering->form($form->node(3 + $offset));
        if ($spec->signature === 'insert_field_spec: SET ident_eq_list') {
            $updates = $clause === null ? [] : (new RetainedUpdates($this->lowering))->read($clause);
            return $this->set(new InsertInto($replace, $priority, $skip, $target, hints: $hints), $spec->node(1), null, $updates, (new \SqlSemantics\Construction\SourceRanges())->span($form->node)[1] ?? 0);
        }
        [$columns, $body] = (new InsertSourceRule($this->lowering))->source($spec->node);
        $updates = $clause === null ? [] : (is_array($body) ? (new RetainedUpdates($this->lowering))->read($clause) : $this->updates($clause));

        return $this->build(new InsertInto($replace, $priority, $skip, $target, $columns, $hints), $body, null, $updates, (new \SqlSemantics\Construction\SourceRanges())->span($form->node)[1] ?? 0);
    }

    /**
     * Builds the statement of rows or of a query.
     *
     * @param list<InsertedRow|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertedRow>|Query $body
     * @param list<Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment> $updates
     * @throws \SqlSemantics\Construction\RejectedInput When a reached call has an invalid identity or signature
     */
    public function build(InsertInto $into, array|Query $body, ?RowAlias $alias, array $updates, int $end = 0): Statement
    {
        if ($body instanceof Query) {
            $updates = \SqlSemantics\Diagnostic\Check::listOf($updates, Assignment::class, 'Query-source assignments were lowered as scalar values.');
            $used = (new \SqlSemantics\Platform\MySql\Rules\Dml\ValueSources())->values($body) ?? $body;
            $problem = (new \SqlSemantics\Platform\MySql\Rules\Call\Structure\CallRoles())->problem($used);
            if ($problem !== null) {
                $origin = $this->lowering->origins->publish()->of($body);
                throw new \SqlSemantics\Construction\RejectedInput($problem, $origin === null ? 0 : $origin->offset + $origin->length, false);
            }
            if ((new \SqlSemantics\Platform\MySql\Rules\Dml\ValueSources())->values($body) === null && (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($body) !== null) {
                throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertQuery(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery($into, $body, $updates)), $end, false);
            }
            return new InsertQuery($into, $body, $updates);
        }

        if ($body !== [] && (array_filter($updates, static fn ($item): bool => $item instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment) !== [] || (new \SqlSemantics\Platform\MySql\Rules\Dml\InsertWidths())->problem($into, $body) !== null || array_filter($body, static fn ($row): bool => $row instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertedRow) !== [])) {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertRows(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows($into, $body, $alias, $updates)), $end, false);
        }
        return new InsertRows($into, array_values(array_filter($body, static fn ($row): bool => $row instanceof InsertedRow)), $alias, \SqlSemantics\Diagnostic\Check::listOf($updates, Assignment::class, 'Ordinary INSERT holds scalar assignments.'));
    }

    /**
     * Builds an ordinary SET write or retains the complete refused template.
     *
     * @param list<Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment> $updates Duplicate-key assignments
     * @throws \SqlSemantics\Construction\RejectedInput When any assignment has a non-scalar role
     */
    public function set(InsertInto $into, Node $list, ?RowAlias $alias, array $updates, int $end): InsertSet
    {
        $assignments = (new RetainedUpdates($this->lowering))->assignments($list);
        if (array_filter([...$assignments, ...$updates], static fn ($item): bool => $item instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment) !== []) {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertSet(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet($into, $assignments, $alias, $updates)), $end, false);
        }
        return new InsertSet($into, \SqlSemantics\Diagnostic\Check::listOf($assignments, Assignment::class, 'Ordinary INSERT holds scalar assignments.'), $alias, \SqlSemantics\Diagnostic\Check::listOf($updates, Assignment::class, 'Ordinary INSERT holds scalar assignments.'));
    }

    /**
     * Lowers the table of a node of `insert2` (5.6).
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function target(Node $insert2): WriteTarget
    {
        $form = $this->lowering->form($insert2);
        $table = match ($form->signature) {
            'insert2: INTO insert_table' => $this->lowering->form($form->node(1)),
            'insert2: insert_table' => $this->lowering->form($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
        if ($table->signature !== 'insert_table: table_name_with_opt_use_partition') {
            throw ImplementationGap::production($table);
        }
        $named = $this->lowering->form($table->node(0));
        if ($named->signature !== 'table_name_with_opt_use_partition: table_ident opt_use_partition') {
            throw ImplementationGap::production($named);
        }

        return new WriteTarget($this->lowering->names->qualified($named->node(0)), null, $this->lowering->queries->partitions($named->node(1)));
    }

    /**
     * Lowers a node of `insert_lock_option` or `replace_lock_option`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function priority(Node $option): ?InsertPriority
    {
        $form = $this->lowering->form($option);
        if ($form->signature === 'replace_lock_option: opt_low_priority') {
            $form = $this->lowering->form($form->node(0));
        }
        if (!array_key_exists($form->signature, self::PRIORITIES)) {
            throw ImplementationGap::production($form);
        }

        return self::PRIORITIES[$form->signature];
    }

    /**
     * Lowers a node of `opt_values_reference`; an absent alias is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function alias(Node $reference): ?RowAlias
    {
        $form = $this->lowering->form($reference);

        return match ($form->signature) {
            'opt_values_reference:' => null,
            'opt_values_reference: AS ident opt_derived_column_list' => new RowAlias($this->lowering->names->identifier($form->node(1)), $this->lowering->queries->columnAliases($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a node of `opt_insert_update` or `opt_insert_update_list`; an absent clause is empty.
     *
     * @return list<Assignment>
     * @throws ImplementationGap When a production has no rule
     */
    public function updates(Node $clause): array
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_insert_update:', 'opt_insert_update_list:' => [],
            'opt_insert_update: ON DUPLICATE_SYM KEY_SYM UPDATE_SYM insert_update_list', 'opt_insert_update_list: ON DUPLICATE_SYM KEY_SYM UPDATE_SYM update_list',
            'opt_insert_update_list: ON_SYM DUPLICATE_SYM KEY_SYM UPDATE_SYM update_list' => (new ValueRule($this->lowering))->assignments($form->node(4)),
            default => throw ImplementationGap::production($form),
        };
    }
}
