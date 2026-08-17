<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Sql\TransactionStatement;

final class FixedRewriter implements SqlRewriter
{
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

    public function __construct(RewritePlan $plan)
    {
        $this->plan = $plan;
    }

    public function rewrite(string $sql): RewritePlan
    {
        return $this->plan;
    }

    public function splitStatements(string $sql): array
    {
        return [$sql];
    }

    public function rewriteMultiple(string $sql): MultiRewritePlan
    {
        return new MultiRewritePlan([$this->plan]);
    }

    public function emptyResultSelect(): string
    {
        return 'SELECT 1 WHERE FALSE';
    }
}
