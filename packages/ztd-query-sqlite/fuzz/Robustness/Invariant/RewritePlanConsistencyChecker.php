<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Exception\SimulationException;
use ZtdQuery\Platform\Sqlite\Rewrite\SqliteIndexHintStripper;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;

/**
 * The rewrite plan consistency checker, as invariant checker.
 */
final class RewritePlanConsistencyChecker implements InvariantChecker
{
    private SqlRewriter $rewriter;

    /**
     * Binds the instance to what it will work from.
     *
     * @param SqlRewriter $rewriter
     */
    public function __construct(SqlRewriter $rewriter)
    {
        $this->rewriter = $rewriter;
    }

    /**
     * Check.
     *
     * @param string $sql
     * @return ?InvariantViolation
     */
    public function check(string $sql): ?InvariantViolation
    {
        try {
            $plan = $this->rewriter->rewrite($sql);
        } catch (SimulationException) {
            return null;
        }

        return $this->checkPlan($plan, $sql);
    }

    /**
     * Check plan.
     *
     * @param RewritePlan $plan
     * @param string $sql
     * @return ?InvariantViolation
     */
    public function checkPlan(RewritePlan $plan, string $sql): ?InvariantViolation
    {
        $kind = $plan->kind();
        $mutation = $plan->mutation();

        if (($kind === QueryKind::WRITE_SIMULATED || $kind === QueryKind::DDL_SIMULATED) && $mutation === null) {
            return new InvariantViolation(
                'INV-L2-02',
                sprintf('%s plan has null mutation', $kind->value),
                $sql,
                ['kind' => $kind->value]
            );
        }

        if (($kind === QueryKind::READ || $kind === QueryKind::SKIPPED) && $mutation !== null) {
            return new InvariantViolation(
                'INV-L2-03',
                sprintf('%s plan has non-null mutation', $kind->value),
                $sql,
                ['kind' => $kind->value, 'mutation_class' => get_class($mutation)]
            );
        }

        if ($plan->sql() === '') {
            return new InvariantViolation(
                'INV-L2-04',
                'Rewritten SQL is empty',
                $sql,
                ['kind' => $kind->value]
            );
        }

        $shadowTables = ['users', 'orders', 'order_items', 'products'];
        $relationParser = new \ZtdQuery\Platform\Sqlite\Parse\SqliteSelectRelationParser();
        $normalizedInput = $relationParser->unqualify($sql, $shadowTables);
        $normalizedPlan = $relationParser->unqualify($plan->sql(), $shadowTables);
        if ($normalizedInput !== $sql && $normalizedPlan !== $plan->sql()) {
            return new InvariantViolation(
                'INV-L2-07',
                'schema-qualified shadow source survived rewrite',
                $sql,
                ['rewrite_sql' => $plan->sql()]
            );
        }

        $hintStripper = new SqliteIndexHintStripper();
        $strippedInput = $hintStripper->strip($sql, $shadowTables);
        $strippedPlan = $hintStripper->strip($plan->sql(), $shadowTables);
        if ($strippedInput !== $sql && $strippedPlan !== $plan->sql()) {
            return new InvariantViolation(
                'INV-L2-08',
                'shadow source index hint survived rewrite',
                $sql,
                ['rewrite_sql' => $plan->sql()]
            );
        }

        return null;
    }
}
