<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use Fuzz\ReportingMysqli;
use mysqli;
use mysqli_sql_exception;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Rewrite\MySqlQueryGuard;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\SqlRewriter;

/**
 * The no syntax error on rewrite checker.
 */
final class NoSyntaxErrorOnRewriteChecker
{
    private MySqlQueryGuard $guard;
    private SqlRewriter $rewriter;
    private ReportingMysqli $rawConnection;

    /**
     * Binds the instance to what it will work from.
     *
     * @param MySqlQueryGuard $guard
     * @param SqlRewriter $rewriter
     * @param mysqli $rawMysqli
     */
    public function __construct(MySqlQueryGuard $guard, SqlRewriter $rewriter, mysqli $rawMysqli)
    {
        $this->guard = $guard;
        $this->rewriter = $rewriter;
        $this->rawConnection = new ReportingMysqli($rawMysqli);
    }

    /**
     * Answers what the rewritten statement breaks, where it breaks nothing.
     *
     * A statement ZTD refuses is not rewritten, so there is nothing to check.
     *
     * @param string $sql Statement as it was written
     *
     * @return InvariantViolation|null What the rewrite broke, or null where it broke nothing
     */
    public function check(string $sql): ?InvariantViolation
    {
        $kind = $this->guard->classify($sql);

        if ($kind === null || $kind === QueryKind::SKIPPED) {
            return null;
        }

        try {
            $plan = $this->rewriter->rewrite($sql);
        } catch (UnsupportedSqlException | UnknownSchemaException) {
            return null;
        }

        $rewrittenSql = $plan->sql();

        try {
            $this->rawConnection()->prepareAndClose($rewrittenSql);
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1064) {
                return new InvariantViolation(
                    'INV-L5-02',
                    'Rewritten SQL has syntax error (MySQL 1064)',
                    $sql,
                    [
                        'original_sql' => $sql,
                        'rewritten_sql' => $rewrittenSql,
                        'classify_kind' => $kind->value,
                        'mysql_error' => $e->getMessage(),
                    ]
                );
            }
            // Other MySQL errors are acceptable (semantic errors, missing tables, etc.)
        }

        return null;
    }
    /**
     * Answers the connection ZTD is not in front of, as something that can fail.
     *
     * @return ReportingMysqli The raw connection, saying how it fails
     */
    public function rawConnection(): ReportingMysqli
    {
        return $this->rawConnection;
    }
}
