<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\DeleteOption;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Statement;

/**
 * Lowers UPDATE and DELETE of every release.
 *
 * Rule: MYSQL-CHANGE-LOWERING-001. Scope: update, delete, single_multi,
 * table_wild_list, table_wild_one, delete_limit_clause (5.6); update_stmt,
 * delete_stmt; opt_low_priority, opt_delete_options, opt_delete_option. An
 * UPDATE is Update; a DELETE with one table after FROM is Delete; the two
 * multiple-table spellings are MultipleDelete with their form. Clauses
 * are lowered by the query family. Terminates: the parts are strict
 * subtrees; option lists are flattened by MYSQL-DML-LIST-001. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/update.html,
 * https://dev.mysql.com/doc/refman/8.4/en/delete.html,
 * https://dev.mysql.com/doc/refman/5.6/en/delete.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Dml
 */
final class ChangeRule
{
    /**
     * The positions of WITH, LOW_PRIORITY, IGNORE, the table references, the assignments, WHERE, ORDER BY and LIMIT of UPDATE.
     */
    private const UPDATES = [
        'update: UPDATE_SYM opt_low_priority opt_ignore join_table_list SET update_list where_clause opt_order_clause delete_limit_clause' => [null, 1, 2, 3, 5, 6, 7, 8],
        'update_stmt: UPDATE_SYM opt_low_priority opt_ignore join_table_list SET update_list opt_where_clause opt_order_clause opt_simple_limit' => [null, 1, 2, 3, 5, 6, 7, 8],
        'update_stmt: opt_with_clause UPDATE_SYM opt_low_priority opt_ignore table_reference_list SET_SYM update_list opt_where_clause opt_order_clause opt_simple_limit' => [0, 2, 3, 4, 6, 7, 8, 9],
    ];

    /**
     * The positions of WITH, the options, the table, the alias, the partitions, WHERE, ORDER BY and LIMIT of a single-table DELETE.
     */
    private const SINGLE = [
        'delete_stmt: DELETE_SYM opt_delete_options FROM table_ident opt_use_partition opt_where_clause opt_order_clause opt_simple_limit' => [null, 1, 3, null, 4, 5, 6, 7],
        'delete_stmt: opt_with_clause DELETE_SYM opt_delete_options FROM table_ident opt_table_alias opt_use_partition opt_where_clause opt_order_clause opt_simple_limit' => [0, 2, 4, 5, 6, 7, 8, 9],
    ];

    /**
     * The form and the positions of WITH, the options, the tables to delete from, the table references and WHERE of a multiple-table DELETE.
     */
    private const MULTIPLE = [
        'delete_stmt: DELETE_SYM opt_delete_options table_alias_ref_list FROM join_table_list opt_where_clause' => [MultipleDeleteForm::BeforeFrom, null, 1, 2, 4, 5],
        'delete_stmt: DELETE_SYM opt_delete_options FROM table_alias_ref_list USING join_table_list opt_where_clause' => [MultipleDeleteForm::Using, null, 1, 3, 5, 6],
        'delete_stmt: opt_with_clause DELETE_SYM opt_delete_options table_alias_ref_list FROM table_reference_list opt_where_clause' => [MultipleDeleteForm::BeforeFrom, 0, 2, 3, 5, 6],
        'delete_stmt: opt_with_clause DELETE_SYM opt_delete_options FROM table_alias_ref_list USING table_reference_list opt_where_clause' => [MultipleDeleteForm::Using, 0, 2, 4, 6, 7],
    ];

    /**
     * The DELETE options by production.
     */
    private const OPTIONS = ['opt_delete_option: QUICK' => DeleteOption::Quick, 'opt_delete_option: LOW_PRIORITY' => DeleteOption::LowPriority, 'opt_delete_option: IGNORE_SYM' => DeleteOption::Ignore];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of `update` or `update_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function update(Form $form): Update
    {
        [$with, $low, $ignore, $tables, $list, $where, $order, $limit] = self::UPDATES[$form->signature] ?? throw ImplementationGap::production($form);
        $queries = $this->lowering->queries;

        return new Update(
            $with === null ? null : $queries->with($form->node($with)),
            $this->lowPriority($form->node($low)),
            $this->lowering->options->present($form->node($ignore)),
            $queries->tables($form->node($tables)),
            (new ValueRule($this->lowering))->assignments($form->node($list)),
            $queries->where($form->node($where)),
            $queries->ordering($form->node($order)),
            $this->limit($form->node($limit)),
        );
    }

    /**
     * Lowers a node of `delete_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function delete(Form $form): Statement
    {
        $queries = $this->lowering->queries;
        $single = self::SINGLE[$form->signature] ?? null;
        if ($single !== null) {
            [$with, $options, $table, $alias, $partitions, $where, $order, $limit] = $single;
            $target = new WriteTarget($this->lowering->names->qualified($form->node($table)), $alias === null ? null : $queries->alias($form->node($alias)), $queries->partitions($form->node($partitions)), $alias === null ? AliasMark::As : $queries->mark($form->node($alias)));

            return new Delete($with === null ? null : $queries->with($form->node($with)), $this->options($form->node($options)), $target, $queries->where($form->node($where)), $queries->ordering($form->node($order)), $queries->limit($form->node($limit)));
        }
        [$spelling, $with, $options, $targets, $tables, $where] = self::MULTIPLE[$form->signature] ?? throw ImplementationGap::production($form);

        return new MultipleDelete(
            $with === null ? null : $queries->with($form->node($with)),
            $this->options($form->node($options)),
            $this->lowering->dml->deleteTargets($form->node($targets)),
            $spelling,
            $queries->tables($form->node($tables)),
            $queries->where($form->node($where)),
        );
    }

    /**
     * Lowers a node of `delete` (5.6).
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function legacyDelete(Form $form): Statement
    {
        if ($form->signature !== 'delete: DELETE_SYM opt_delete_options single_multi') {
            throw ImplementationGap::production($form);
        }
        $options = $this->options($form->node(1));
        $body = $this->lowering->form($form->node(2));
        $queries = $this->lowering->queries;

        return match ($body->signature) {
            'single_multi: FROM table_ident opt_use_partition where_clause opt_order_clause delete_limit_clause' => new Delete(
                null,
                $options,
                new WriteTarget($this->lowering->names->qualified($body->node(1)), null, $queries->partitions($body->node(2))),
                $queries->where($body->node(3)),
                $queries->ordering($body->node(4)),
                $this->limit($body->node(5)),
            ),
            'single_multi: table_wild_list FROM join_table_list where_clause' => new MultipleDelete(null, $options, $this->wildTargets($body->node(0)), MultipleDeleteForm::BeforeFrom, $queries->tables($body->node(2)), $queries->where($body->node(3))),
            'single_multi: FROM table_alias_ref_list USING join_table_list where_clause' => new MultipleDelete(null, $options, $this->lowering->dml->deleteTargets($body->node(1)), MultipleDeleteForm::Using, $queries->tables($body->node(3)), $queries->where($body->node(4))),
            default => throw ImplementationGap::production($body),
        };
    }

    /**
     * Lowers a node of `opt_delete_options`.
     *
     * @return list<DeleteOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $list): array
    {
        $options = [];
        foreach ((new ListRule($this->lowering))->items($list, ['opt_delete_options:', 'opt_delete_options: opt_delete_option opt_delete_options']) as $item) {
            $form = $this->lowering->form($item);
            $options[] = self::OPTIONS[$form->signature] ?? throw ImplementationGap::production($form);
        }

        return $options;
    }

    /**
     * Lowers a node of `opt_low_priority`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function lowPriority(Node $option): bool
    {
        $form = $this->lowering->form($option);

        return match ($form->signature) {
            'opt_low_priority:' => false,
            'opt_low_priority: LOW_PRIORITY' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a LIMIT clause: a node of `delete_limit_clause` (5.6) or `opt_simple_limit`; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function limit(Node $clause): ?Limit
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'delete_limit_clause:' => null,
            'delete_limit_clause: LIMIT limit_option' => new RowLimit($this->lowering->queries->limitValue($form->node(1))),
            default => $this->lowering->queries->limit($clause),
        };
    }

    /**
     * Lowers the tables to delete from of a node of `table_wild_list` (5.6).
     *
     * @return list<QualifiedName>
     * @throws ImplementationGap When a production has no rule
     */
    public function wildTargets(Node $list): array
    {
        $targets = [];
        foreach ((new ListRule($this->lowering))->items($list, ['table_wild_list: table_wild_one', 'table_wild_list: table_wild_list , table_wild_one']) as $item) {
            $form = $this->lowering->form($item);
            $names = $this->lowering->names;
            $targets[] = match ($form->signature) {
                'table_wild_one: ident opt_wild' => new QualifiedName($names->identifier($form->node(0))),
                'table_wild_one: ident . ident opt_wild' => new QualifiedName($names->identifier($form->node(2)), $names->identifier($form->node(0))),
                default => throw ImplementationGap::production($form),
            };
        }

        return $targets;
    }
}
