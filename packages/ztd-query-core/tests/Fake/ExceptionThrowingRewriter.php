<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;

/**
 * Rewriter that throws an exception when rewrite() is called.
 */
final class ExceptionThrowingRewriter implements SqlRewriter
{
    private \Throwable $exception;

    public function __construct(\Throwable $exception)
    {
        $this->exception = $exception;
    }

    public function rewrite(string $sql): RewritePlan
    {
        throw $this->exception;
    }

    public function rewriteMultiple(string $sql): MultiRewritePlan
    {
        throw $this->exception;
    }
}
