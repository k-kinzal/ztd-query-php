<?php

declare(strict_types=1);

namespace Tests\Fake;

use Throwable;
use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Sql\TransactionStatement;

/**
 * Rewriter that throws an exception when rewrite() is called.
 */
final class ExceptionThrowingRewriter implements SqlRewriter
{
    public function transactionStatement(string $sql): ?TransactionStatement
    {
        return null;
    }

    private Throwable $exception;

    public function __construct(Throwable $exception)
    {
        $this->exception = $exception;
    }

    public function rewrite(string $sql): RewritePlan
    {
        throw $this->exception;
    }

    public function splitStatements(string $sql): array
    {
        return [$sql];
    }

    public function rewriteMultiple(string $sql): MultiRewritePlan
    {
        throw $this->exception;
    }

    public function emptyResultSelect(): string
    {
        return 'SELECT 1 WHERE FALSE';
    }
}
