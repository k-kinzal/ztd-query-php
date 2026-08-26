<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use Throwable;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\SqlRewriter;

/**
 * The rewrite exception type checker, as invariant checker.
 */
final class RewriteExceptionTypeChecker implements InvariantChecker
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
            $this->rewriter->rewrite($sql);
            return null;
        } catch (UnsupportedSqlException|UnknownSchemaException) {
            return null;
        } catch (Throwable $e) {
            return new InvariantViolation(
                'INV-L2-01',
                'rewrite() threw an unexpected exception type',
                $sql,
                [
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                    'exception_trace' => $e->getTraceAsString(),
                ]
            );
        }
    }
}
