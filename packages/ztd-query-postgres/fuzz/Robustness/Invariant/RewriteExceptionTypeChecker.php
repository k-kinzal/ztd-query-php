<?php

declare (strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Postgres\PgSqlRewriter;

/**
 * Rewrite exception type checker for PostgreSQL queries.
 */
final class RewriteExceptionTypeChecker implements InvariantChecker
{
    private PgSqlRewriter $rewriter;
    /**
     * Initializes the collaborators and state used by this rewrite exception type checker.
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
            $this->rewriter->rewrite($sql);
            return null;
        } catch (UnsupportedSqlException|UnknownSchemaException) {
            return null;
        }
    }
}
