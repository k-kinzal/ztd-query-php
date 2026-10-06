<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition\View;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Spine;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\View\AlterView;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Platform\MySql\Statement\View\DropView;
use SqlSemantics\Platform\MySql\Statement\View\ViewAlgorithm;
use SqlSemantics\Platform\MySql\Statement\View\ViewCheckOption;
use SqlSemantics\Platform\MySql\Statement\View\ViewDefinition;
use SqlSemantics\Platform\MySql\Statement\View\ViewSecurity;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers CREATE VIEW, ALTER VIEW and DROP VIEW.
 *
 * Rule: MYSQL-VIEW-LOWERING-001. Scope: view_replace_or_algorithm,
 * view_replace, view_algorithm, view_suid, view_tail, view_list_opt,
 * view_list, view_select, view_query_block, view_check_option,
 * alter_view_stmt, drop_view_stmt, and the `alter` and `drop` alternatives
 * of views (5.x). The definer comes from the leaf rules, the query from the
 * query family. Constructs: CreateView, AlterView, DropView, ViewDefinition.
 * Terminates: every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-view.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-view.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-view.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ViewRule
{
    /**
     * The algorithm clauses.
     */
    private const ALGORITHMS = [
        'view_algorithm: ALGORITHM_SYM EQ UNDEFINED_SYM' => ViewAlgorithm::Undefined, 'view_algorithm: ALGORITHM_SYM EQ MERGE_SYM' => ViewAlgorithm::Merge,
        'view_algorithm: ALGORITHM_SYM EQ TEMPTABLE_SYM' => ViewAlgorithm::TempTable,
    ];

    /**
     * The check option clauses.
     */
    private const CHECKS = [
        'view_check_option:' => null, 'view_check_option: WITH CHECK_SYM OPTION' => ViewCheckOption::Unqualified,
        'view_check_option: WITH CASCADED CHECK_SYM OPTION' => ViewCheckOption::Cascaded, 'view_check_option: WITH LOCAL_SYM CHECK_SYM OPTION' => ViewCheckOption::Local,
    ];

    /**
     * The security clauses.
     */
    private const SECURITY = [
        'view_suid:' => null, 'view_suid: SQL_SYM SECURITY_SYM DEFINER_SYM' => ViewSecurity::Definer, 'view_suid: SQL_SYM SECURITY_SYM INVOKER_SYM' => ViewSecurity::Invoker,
    ];

    /**
     * The ALTER VIEW productions: the position of the algorithm clause, of the definer and of the tail.
     */
    private const ALTERS = [
        'alter: ALTER view_algorithm definer_opt view_tail' => [1, 2, 3], 'alter: ALTER definer_opt view_tail' => [null, 1, 2],
        'alter_view_stmt: ALTER view_algorithm definer_opt view_tail' => [1, 2, 3], 'alter_view_stmt: ALTER definer_opt view_tail' => [null, 1, 2],
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers CREATE VIEW: a node of `view_tail`, the node of `view_replace_or_algorithm` when one is written, and the definer.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function create(Node $tail, ?Node $prefix, ?Account $definer): CreateView
    {
        [$replace, $algorithm] = $prefix === null ? [false, null] : $this->prefix($prefix);

        return new CreateView($this->definition($tail, $algorithm, $definer), $replace);
    }

    /**
     * Lowers the OR REPLACE and ALGORITHM prefix: a node of `view_replace_or_algorithm`.
     *
     * @return array{bool, ViewAlgorithm|null}
     * @throws ImplementationGap When a production has no rule
     */
    public function prefix(Node $prefix): array
    {
        $form = $this->lowering->productions->form($prefix);

        return match ($form->signature) {
            'view_replace_or_algorithm: view_replace' => [$this->replace($form->node(0)), null],
            'view_replace_or_algorithm: view_replace view_algorithm' => [$this->replace($form->node(0)), $this->algorithm($form->node(1))],
            'view_replace_or_algorithm: view_algorithm' => [false, $this->algorithm($form->node(0))],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Confirms OR REPLACE: a node of `view_replace`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function replace(Node $replace): bool
    {
        $form = $this->lowering->productions->form($replace);

        return $form->signature === 'view_replace: OR_SYM REPLACE' || $form->signature === 'view_replace: OR_SYM REPLACE_SYM' ? true : throw ImplementationGap::production($form);
    }

    /**
     * Lowers an algorithm clause: a node of `view_algorithm`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function algorithm(Node $algorithm): ViewAlgorithm
    {
        $form = $this->lowering->productions->form($algorithm);

        return self::ALGORITHMS[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers the definition after the definer: a node of `view_tail`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function definition(Node $tail, ?ViewAlgorithm $algorithm, ?Account $definer): ViewDefinition
    {
        $form = $this->lowering->productions->form($tail);
        $suid = $this->lowering->productions->form($form->node(0));
        if (!array_key_exists($suid->signature, self::SECURITY)) {
            throw ImplementationGap::production($suid);
        }
        [$exists, $name, $columns, $body] = match ($form->signature) {
            'view_tail: view_suid VIEW_SYM table_ident view_list_opt AS view_select' => [false, $form->node(2), $this->columns($form->node(3)), $form->node(5)],
            'view_tail: view_suid VIEW_SYM table_ident opt_derived_column_list AS view_query_block' => [false, $form->node(2), $this->derived($form->node(3)), $form->node(5)],
            'view_tail: view_suid VIEW_SYM opt_if_not_exists table_ident opt_derived_column_list AS view_query_block' => [$this->lowering->options->present($form->node(2)), $form->node(3), $this->derived($form->node(4)), $form->node(6)],
            default => throw ImplementationGap::production($form),
        };
        $query = $this->lowering->productions->form($body);
        if ($query->signature !== 'view_select: view_select_aux view_check_option' && $query->signature !== 'view_query_block: query_expression_with_opt_locking_clauses view_check_option') {
            throw ImplementationGap::production($query);
        }
        $check = $this->lowering->productions->form($query->node(1));
        if (!array_key_exists($check->signature, self::CHECKS)) {
            throw ImplementationGap::production($check);
        }

        return new ViewDefinition($this->lowering->names->qualified($name), $this->lowering->queries->query($query->node(0)), $columns, $algorithm, $definer, self::SECURITY[$suid->signature], self::CHECKS[$check->signature], $exists);
    }

    /**
     * Lowers a 5.x column list: a node of `view_list_opt`; none is null.
     *
     * @return list<Name>|null
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $list): ?array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature === 'view_list_opt:') {
            return null;
        }
        if ($form->signature !== 'view_list_opt: ( view_list )') {
            throw ImplementationGap::production($form);
        }
        $names = [];
        foreach ((new Spine($this->lowering))->items($form->node(1), ['view_list: ident', 'view_list: view_list , ident'], ['ident']) as $ident) {
            $names[] = $this->lowering->names->identifier($ident);
        }

        return $names;
    }

    /**
     * Lowers an 8.0 column list: a node of `opt_derived_column_list`; none is null.
     *
     * @return list<Name>|null
     * @throws ImplementationGap When a production has no rule
     */
    public function derived(Node $list): ?array
    {
        $names = $this->lowering->queries->columnAliases($list);

        return $names === [] ? null : $names;
    }

    /**
     * Lowers ALTER VIEW: the form of a 5.x `alter` alternative or of `alter_view_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function alter(Form $form): AlterView
    {
        [$algorithm, $definer, $tail] = self::ALTERS[$form->signature] ?? throw ImplementationGap::production($form);

        return new AlterView($this->definition($form->node($tail), $algorithm === null ? null : $this->algorithm($form->node($algorithm)), $this->lowering->users->definer($form->node($definer))));
    }

    /**
     * Lowers DROP VIEW: the form of the 5.x `drop` alternative or of `drop_view_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function drop(Form $form): DropView
    {
        if ($form->signature !== 'drop: DROP VIEW_SYM if_exists table_list opt_restrict' && $form->signature !== 'drop_view_stmt: DROP VIEW_SYM if_exists table_list opt_restrict') {
            throw ImplementationGap::production($form);
        }

        return new DropView($this->lowering->names->qualifiedList($form->node(3)), $this->lowering->options->present($form->node(2)), $this->lowering->options->dropBehavior($form->node(4)));
    }
}
