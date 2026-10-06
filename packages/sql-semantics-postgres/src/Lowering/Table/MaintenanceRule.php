<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\CreateAssertion;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\AlterStatistics;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ColumnKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateStatistics;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ExpressionKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\TruncateTable;

/**
 * Lowers TRUNCATE, extended statistics and CREATE ASSERTION.
 *
 * Rule: PG-TABLE-MAINTENANCE-LOWER-001. Scope: `TruncateStmt`,
 * `opt_restart_seqs`, `CreateStatsStmt`, `stats_params`, `stats_param`,
 * `AlterStatsStmt`, `CreateAssertionStmt`. The optional TABLE of TRUNCATE is
 * noise (LeafNoise `opt_table`). Termination: lists are flattened
 * iteratively. Source: https://www.postgresql.org/docs/17/sql-truncate.html,
 * https://www.postgresql.org/docs/17/sql-createstatistics.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class MaintenanceRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `TruncateStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function truncate(Node $statement): TruncateTable
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'TruncateStmt: TRUNCATE opt_table relation_expr_list opt_restart_seqs opt_drop_behavior') {
            throw ImplementationGap::production($form);
        }
        $restart = $this->lowering->productions->form($form->node(3));

        return new TruncateTable(
            $this->lowering->queries->relations($form->node(2)),
            match ($restart->signature) {
                'opt_restart_seqs: RESTART IDENTITY_P' => true,
                'opt_restart_seqs: CONTINUE_P IDENTITY_P' => false,
                'opt_restart_seqs:' => null,
                default => throw ImplementationGap::production($restart),
            },
            $this->lowering->flags->dropBehavior($form->node(4)),
        );
    }

    /**
     * Lowers `CreateStatsStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statistics(Node $statement): CreateStatistics
    {
        $form = $this->lowering->productions->form($statement);
        [$name, $at] = match ($form->signature) {
            'CreateStatsStmt: CREATE STATISTICS opt_qualified_name opt_name_list ON stats_params FROM from_list' => [$this->lowering->names->optionalDotted($form->node(2)), 0],
            'CreateStatsStmt: CREATE STATISTICS IF_P NOT EXISTS any_name opt_name_list ON stats_params FROM from_list' => [$this->lowering->names->dotted($form->node(5)), 3],
            default => throw ImplementationGap::production($form),
        };
        $keys = [];
        foreach ($this->lowering->items($form->node(5 + $at), 'stats_params: stats_param', 'stats_params: stats_params , stats_param') as $item) {
            $param = $this->lowering->productions->form($item);
            $keys[] = match ($param->signature) {
                'stats_param: ColId' => new ColumnKey($this->lowering->names->name($param->node(0))),
                'stats_param: func_expr_windowless' => new ExpressionKey($this->lowering->invocations->call($param->node(0)), false),
                'stats_param: ( a_expr )' => new ExpressionKey($this->lowering->expressions->expression($param->node(1))),
                default => throw ImplementationGap::production($param),
            };
        }

        return new CreateStatistics($name, $keys, $this->lowering->queries->from($form->node(7 + $at)), $this->lowering->names->names($form->node(3 + $at)), $at !== 0);
    }

    /**
     * Lowers `AlterStatsStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function alterStatistics(Node $statement): AlterStatistics
    {
        $form = $this->lowering->productions->form($statement);
        $at = match ($form->signature) {
            'AlterStatsStmt: ALTER STATISTICS any_name SET STATISTICS SignedIconst', 'AlterStatsStmt: ALTER STATISTICS any_name SET STATISTICS set_statistics_value' => 0,
            'AlterStatsStmt: ALTER STATISTICS IF_P EXISTS any_name SET STATISTICS SignedIconst', 'AlterStatsStmt: ALTER STATISTICS IF_P EXISTS any_name SET STATISTICS set_statistics_value' => 2,
            default => throw ImplementationGap::production($form),
        };

        return new AlterStatistics($this->lowering->names->dotted($form->node(2 + $at)), (new AlterCommandRule($this->lowering))->statistics($form->node(5 + $at)), $at !== 0);
    }

    /**
     * Lowers `CreateAssertionStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function assertion(Node $statement): CreateAssertion
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'CreateAssertionStmt: CREATE ASSERTION any_name CHECK ( a_expr ) ConstraintAttributeSpec') {
            throw ImplementationGap::production($form);
        }

        return new CreateAssertion($this->lowering->names->dotted($form->node(2)), $this->lowering->expressions->expression($form->node(5)), $this->lowering->tables->constraintAttributes($form->node(7)));
    }
}
