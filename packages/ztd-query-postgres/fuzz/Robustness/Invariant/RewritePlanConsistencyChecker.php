<?php

declare (strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Platform\Postgres\PgSqlRewriter;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;

/**
 * Rewrite plan consistency checker for PostgreSQL queries.
 */
final class RewritePlanConsistencyChecker implements InvariantChecker
{
    private PgSqlRewriter $rewriter;
    /**
     * Initializes the collaborators and state used by this rewrite plan consistency checker.
     */
    public function __construct(PgSqlRewriter $rewriter)
    {
        $this->rewriter = $rewriter;
    }
    /**
     * Check.
     */
    public function check(string $sql): ?InvariantViolation
    {
        try {
            $plan = $this->rewriter->rewrite($sql);
        } catch (\ZtdQuery\Exception\UnknownSchemaException|\ZtdQuery\Exception\UnsupportedSqlException) {
            return null;
        }
        return $this->checkPlan($plan, $sql);
    }
    /**
     * Check plan.
     */
    public function checkPlan(RewritePlan $plan, string $sql): ?InvariantViolation
    {
        $kind = $plan->kind();
        $mutation = $plan->mutation();
        if (($kind === QueryKind::WRITE_SIMULATED || $kind === QueryKind::DDL_SIMULATED) && $mutation === null) {
            return new InvariantViolation('INV-L2-02', sprintf('%s plan has null mutation', $kind->value), $sql, ['kind' => $kind->value]);
        }
        if (($kind === QueryKind::READ || $kind === QueryKind::SKIPPED) && $mutation !== null) {
            return new InvariantViolation('INV-L2-03', sprintf('%s plan has non-null mutation', $kind->value), $sql, ['kind' => $kind->value, 'mutation_class' => get_class($mutation)]);
        }
        if ($plan->sql() === '') {
            return new InvariantViolation('INV-L2-04', 'Rewritten SQL is empty', $sql, ['kind' => $kind->value]);
        }
        $shadowTables = ['users', 'orders', 'order_items', 'products'];
        $relationParser = new \ZtdQuery\Platform\Postgres\PgSqlSelectRelationParser();
        $normalizedInput = $relationParser->unqualify($sql, $shadowTables);
        $normalizedPlan = $relationParser->unqualify($plan->sql(), $shadowTables);
        if ($normalizedInput !== $sql && $normalizedPlan !== $plan->sql()) {
            return new InvariantViolation('INV-L2-07', 'schema-qualified shadow source survived rewrite', $sql, ['rewrite_sql' => $plan->sql()]);
        }
        return null;
    }
}
