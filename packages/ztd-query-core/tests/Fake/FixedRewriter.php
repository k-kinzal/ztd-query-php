<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Sql\TransactionStatement;

/**
 * A rewriter that answers the same plan whatever it is given.
 *
 * A test about what the session does with a plan has no interest in how the
 * plan was arrived at.
 */
final class FixedRewriter implements SqlRewriter
{
    /**
     * Transaction statement.
     *
     * @param string $sql
     * @return ?TransactionStatement
     */
    public function transactionStatement(string $sql): ?TransactionStatement
    {
        return null;
    }

    /**
     * Predefined plan to return from rewrite().
     *
     * @var RewritePlan
     */
    private RewritePlan $plan;

    /**
     * Binds the instance to what it will work from.
     *
     * @param RewritePlan $plan
     */
    public function __construct(RewritePlan $plan)
    {
        $this->plan = $plan;
    }

    /**
     * Rewrite.
     *
     * @param string $sql
     * @return RewritePlan
     */
    public function rewrite(string $sql): RewritePlan
    {
        return $this->plan;
    }

    /**
     * Split statements.
     *
     * @param string $sql
     */
    public function splitStatements(string $sql): array
    {
        return [$sql];
    }

    /**
     * Rewrite multiple.
     *
     * @param string $sql
     * @return MultiRewritePlan
     */
    public function rewriteMultiple(string $sql): MultiRewritePlan
    {
        return new MultiRewritePlan([$this->plan]);
    }

    /**
     * Empty result select.
     *
     * @return string
     */
    public function emptyResultSelect(): string
    {
        return 'SELECT 1 WHERE FALSE';
    }
}
