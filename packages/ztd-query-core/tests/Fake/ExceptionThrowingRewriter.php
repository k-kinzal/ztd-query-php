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

    private Throwable $exception;

    /**
     * Binds the instance to what it will work from.
     *
     * @param Throwable $exception
     */
    public function __construct(Throwable $exception)
    {
        $this->exception = $exception;
    }

    /**
     * Rewrite.
     *
     * @param string $sql
     * @return RewritePlan
     */
    public function rewrite(string $sql): RewritePlan
    {
        throw $this->exception;
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
        throw $this->exception;
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
