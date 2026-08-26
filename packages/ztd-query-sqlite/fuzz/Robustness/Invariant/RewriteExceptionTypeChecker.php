<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

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
     * Checks that rewriting only ever fails by refusing.
     *
     * The two refusals are what a caller is told to expect, so they are
     * caught. Anything else escapes, and the fuzzer records it as the crash
     * it is -- which is the invariant.
     *
     * @param string $sql Statement the fuzzer drew
     *
     * @return InvariantViolation|null Always nothing, because failing otherwise is not returning
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
