<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Sqlite\SqliteRewriter;

/**
 * Permits documented unsupported-SQL and missing-schema rejections.
 */
final class RewriteExceptionTypeChecker implements InvariantChecker
{
    private SqliteRewriter $rewriter;

    /**
     * Binds the collaborator used by this invariant check.
     */
    public function __construct(SqliteRewriter $rewriter)
    {
        $this->rewriter = $rewriter;
    }

    /**
     * Allows only unsupported SQL and absent fixture schemas; every other exception is a finding.
     */
    public function check(string $sql): ?InvariantViolation
    {
        try {
            $this->rewriter->rewrite($sql);
        } catch (UnsupportedSqlException | UnknownSchemaException) {
            return null;
        }

        return null;
    }
}
