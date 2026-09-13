<?php

declare (strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Platform\Postgres\PgSqlRewriter;
use ZtdQuery\Shadow\Mutation\MultiTruncateMutation;

/**
 * Truncate target consistency checker for PostgreSQL queries.
 */
final class TruncateTargetConsistencyChecker implements InvariantChecker
{
    /**
     * Initializes the collaborators and state used by this truncate target consistency checker.
     */
    public function __construct(private readonly PgSqlRewriter $rewriter)
    {
    }
    /**
     * Check.
     */
    public function check(string $sql): ?InvariantViolation
    {
        $expectedCount = (new \Fuzz\Input\TruncateTargets())->targetCount($sql);
        if ($expectedCount === null || $expectedCount < 2) {
            return null;
        }
        try {
            $mutation = $this->rewriter->rewrite($sql)->mutation();
        } catch (\ZtdQuery\Exception\UnknownSchemaException|\ZtdQuery\Exception\UnsupportedSqlException) {
            return null;
        }
        if (!$mutation instanceof MultiTruncateMutation) {
            return new InvariantViolation('PG-TRUNCATE-TARGETS', 'multi-table TRUNCATE did not produce a multi-target mutation', $sql, ['expected_targets' => $expectedCount]);
        }
        $actualCount = count($mutation->tableNames());
        if ($actualCount !== $expectedCount) {
            return new InvariantViolation('PG-TRUNCATE-TARGETS', 'TRUNCATE mutation target count differs from the SQL target list', $sql, ['expected_targets' => $expectedCount, 'actual_targets' => $actualCount]);
        }
        return null;
    }

}
