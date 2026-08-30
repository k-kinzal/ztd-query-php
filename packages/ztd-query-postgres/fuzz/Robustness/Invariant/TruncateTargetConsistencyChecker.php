<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Exception\SimulationException;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Shadow\Mutation\Table\MultiTruncateMutation;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The truncate target consistency checker, as invariant checker.
 */
final class TruncateTargetConsistencyChecker implements InvariantChecker
{
    /**
     * Binds the instance to what it will work from.
     *
     * @param SqlRewriter $rewriter
     */
    public function __construct(
        private readonly SqlRewriter $rewriter,
    ) {
    }

    /**
     * Check.
     *
     * @param string $sql
     * @return ?InvariantViolation
     */
    public function check(string $sql): ?InvariantViolation
    {
        $expectedCount = $this->targetCount($sql);
        if ($expectedCount === null || $expectedCount < 2) {
            return null;
        }

        try {
            $mutation = $this->rewriter->rewrite($sql)->mutation();
        } catch (SimulationException) {
            return null;
        }

        if (!$mutation instanceof MultiTruncateMutation) {
            return new InvariantViolation(
                'PG-TRUNCATE-TARGETS',
                'multi-table TRUNCATE did not produce a multi-target mutation',
                $sql,
                ['expected_targets' => (string) $expectedCount],
            );
        }

        $actualCount = count($mutation->tableNames());
        if ($actualCount !== $expectedCount) {
            return new InvariantViolation(
                'PG-TRUNCATE-TARGETS',
                'TRUNCATE mutation target count differs from the SQL target list',
                $sql,
                ['expected_targets' => (string) $expectedCount, 'actual_targets' => (string) $actualCount],
            );
        }

        return null;
    }

    /**
     * Answers how many tables a TRUNCATE names.
     *
     * @param string $sql Statement being read, as written
     *
     * @return int|null What it answers
     */
    public function targetCount(string $sql): ?int
    {
        $stream = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create());
        if ($stream->firstTopLevelKeyword() !== 'TRUNCATE') {
            return null;
        }

        $targetList = $stream->topLevelClause(
            ['TRUNCATE'],
            [['RESTART', 'IDENTITY'], ['CONTINUE', 'IDENTITY'], ['CASCADE'], ['RESTRICT']],
        );
        if ($targetList === null || $targetList === '') {
            return 0;
        }

        return count(SqlTokenStream::tokenize($targetList, PgSqlLexerProfile::create())->splitTopLevel());
    }
}
