<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTableAs;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTableAsExecute;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CheckOption;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateMaterializedView;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\RefreshMaterializedView;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the statements that define a relation by a query: CREATE TABLE AS, CREATE VIEW, CREATE MATERIALIZED VIEW, and REFRESH.
 *
 * Rule: PG-CREATE-AS-LOWER-001. Scope: `CreateAsStmt`, `create_as_target`,
 * `opt_with_data`, `CreateMatViewStmt`, `create_mv_target`, `OptNoLog`,
 * `RefreshMatViewStmt`, `ViewStmt`, `opt_check_option`, and the CREATE
 * TABLE ... AS EXECUTE forms of `ExecuteStmt`. Termination: no recursion of
 * its own. Source: https://www.postgresql.org/docs/17/sql-createtableas.html,
 * https://www.postgresql.org/docs/17/sql-createview.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateAsRule
{
    /**
     * The positions of OR REPLACE, the persistence, RECURSIVE, the name, the column list, the options, the query and the check option of each `ViewStmt` production.
     */
    private const VIEWS = [
        'ViewStmt: CREATE OptTemp VIEW qualified_name opt_column_list opt_reloptions AS SelectStmt opt_check_option' => [false, 1, false, 3, 4, 5, 7, 8],
        'ViewStmt: CREATE OR REPLACE OptTemp VIEW qualified_name opt_column_list opt_reloptions AS SelectStmt opt_check_option' => [true, 3, false, 5, 6, 7, 9, 10],
        'ViewStmt: CREATE OptTemp RECURSIVE VIEW qualified_name ( columnList ) opt_reloptions AS SelectStmt opt_check_option' => [false, 1, true, 4, 6, 8, 10, 11],
        'ViewStmt: CREATE OR REPLACE OptTemp RECURSIVE VIEW qualified_name ( columnList ) opt_reloptions AS SelectStmt opt_check_option' => [true, 3, true, 6, 8, 10, 12, 13],
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `CreateAsStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function tableAs(Node $statement): CreateTableAs
    {
        $form = $this->lowering->productions->form($statement);
        $at = match ($form->signature) {
            'CreateAsStmt: CREATE OptTemp TABLE create_as_target AS SelectStmt opt_with_data' => 0,
            'CreateAsStmt: CREATE OptTemp TABLE IF_P NOT EXISTS create_as_target AS SelectStmt opt_with_data' => 3,
            default => throw ImplementationGap::production($form),
        };

        return new CreateTableAs(
            $this->target($form->node(3 + $at)),
            $this->lowering->queries->query($form->node(5 + $at)),
            (new CreateTableRule($this->lowering))->persistence($form->node(1)),
            $at !== 0,
            $this->withData($form->node(6 + $at)),
        );
    }

    /**
     * Builds CREATE TABLE ... AS EXECUTE from its `ExecuteStmt` production and the lowered EXECUTE.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function tableAsExecute(Form $form, Statement $execute): CreateTableAsExecute
    {
        $at = match ($form->signature) {
            'ExecuteStmt: CREATE OptTemp TABLE create_as_target AS EXECUTE name execute_param_clause opt_with_data' => 0,
            'ExecuteStmt: CREATE OptTemp TABLE IF_P NOT EXISTS create_as_target AS EXECUTE name execute_param_clause opt_with_data' => 3,
            default => throw ImplementationGap::production($form),
        };

        return new CreateTableAsExecute(
            $this->target($form->node(3 + $at)),
            $execute,
            (new CreateTableRule($this->lowering))->persistence($form->node(1)),
            $at !== 0,
            $this->withData($form->node(8 + $at)),
        );
    }

    /**
     * Lowers `create_as_target`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function target(Node $target): CreateTarget
    {
        $form = $this->lowering->productions->form($target);
        if ($form->signature !== 'create_as_target: qualified_name opt_column_list table_access_method_clause OptWith OnCommitOption OptTableSpace') {
            throw ImplementationGap::production($form);
        }
        $table = new CreateTableRule($this->lowering);

        return new CreateTarget(
            $this->lowering->names->qualified($form->node(0)),
            $this->lowering->names->names($form->node(1)),
            $table->method($form->node(2)),
            $table->storage($form->node(3)),
            $table->onCommit($form->node(4)),
            (new IndexRule($this->lowering))->tablespace($form->node(5)),
        );
    }

    /**
     * Lowers `opt_with_data`: true for WITH DATA, false for WITH NO DATA, null for nothing.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function withData(Node $clause): ?bool
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'opt_with_data: WITH DATA_P' => true,
            'opt_with_data: WITH NO DATA_P' => false,
            'opt_with_data:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `CreateMatViewStmt` with its `create_mv_target` and `OptNoLog`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function materializedView(Node $statement): CreateMaterializedView
    {
        $form = $this->lowering->productions->form($statement);
        $at = match ($form->signature) {
            'CreateMatViewStmt: CREATE OptNoLog MATERIALIZED VIEW create_mv_target AS SelectStmt opt_with_data' => 0,
            'CreateMatViewStmt: CREATE OptNoLog MATERIALIZED VIEW IF_P NOT EXISTS create_mv_target AS SelectStmt opt_with_data' => 3,
            default => throw ImplementationGap::production($form),
        };
        $target = $this->lowering->productions->form($form->node(4 + $at));
        if ($target->signature !== 'create_mv_target: qualified_name opt_column_list table_access_method_clause opt_reloptions OptTableSpace') {
            throw ImplementationGap::production($target);
        }
        $logging = $this->lowering->productions->form($form->node(1));
        $unlogged = match ($logging->signature) {
            'OptNoLog: UNLOGGED' => true,
            'OptNoLog:' => false,
            default => throw ImplementationGap::production($logging),
        };

        return new CreateMaterializedView(
            $this->lowering->names->qualified($target->node(0)),
            $this->lowering->queries->query($form->node(6 + $at)),
            $this->lowering->names->names($target->node(1)),
            $unlogged,
            $at !== 0,
            (new CreateTableRule($this->lowering))->method($target->node(2)),
            $this->lowering->options->definitions($target->node(3)),
            (new IndexRule($this->lowering))->tablespace($target->node(4)),
            $this->withData($form->node(7 + $at)),
        );
    }

    /**
     * Lowers `RefreshMatViewStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function refresh(Node $statement): RefreshMaterializedView
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'RefreshMatViewStmt: REFRESH MATERIALIZED VIEW opt_concurrently qualified_name opt_with_data') {
            throw ImplementationGap::production($form);
        }

        return new RefreshMaterializedView($this->lowering->names->qualified($form->node(4)), $this->lowering->flags->present($form->node(3)), $this->withData($form->node(5)));
    }

    /**
     * Lowers `ViewStmt` with its `opt_check_option`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function view(Node $statement): CreateView
    {
        $form = $this->lowering->productions->form($statement);
        [$replace, $persistence, $recursive, $name, $columns, $options, $query, $check] = self::VIEWS[$form->signature] ?? throw ImplementationGap::production($form);
        $option = $this->lowering->productions->form($form->node($check));

        return new CreateView(
            $this->lowering->names->qualified($form->node($name)),
            $this->lowering->queries->query($form->node($query)),
            $this->lowering->names->names($form->node($columns)),
            $replace,
            (new CreateTableRule($this->lowering))->persistence($form->node($persistence)),
            $recursive,
            $this->lowering->options->definitions($form->node($options)),
            match ($option->signature) {
                'opt_check_option: WITH CHECK OPTION' => CheckOption::Unqualified,
                'opt_check_option: WITH CASCADED CHECK OPTION' => CheckOption::Cascaded,
                'opt_check_option: WITH LOCAL CHECK OPTION' => CheckOption::Local,
                'opt_check_option:' => null,
                default => throw ImplementationGap::production($option),
            },
        );
    }
}
