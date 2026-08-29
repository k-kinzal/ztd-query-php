<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use mysqli;
use mysqli_sql_exception;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\SqlRewriter;

final class NoSyntaxErrorOnRewriteChecker
{
    private MySqlQueryGuard $guard;
    private SqlRewriter $rewriter;
    private mysqli $rawMysqli;

    public function __construct(MySqlQueryGuard $guard, SqlRewriter $rewriter, mysqli $rawMysqli)
    {
        $this->guard = $guard;
        $this->rewriter = $rewriter;
        $this->rawMysqli = $rawMysqli;
    }

    public function check(string $sql): ?InvariantViolation
    {
        try {
            $kind = $this->guard->classify($sql);
        } catch (\Throwable) {
            return null;
        }

        if ($kind === null || $kind === QueryKind::SKIPPED) {
            return null;
        }

        try {
            $plan = $this->rewriter->rewrite($sql);
        } catch (\Throwable) {
            return null;
        }

        $rewrittenSql = $plan->sql();

        try {
            $stmt = $this->rawMysqli->prepare($rewrittenSql);
            if ($stmt !== false) {
                $stmt->close();
            }
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
}
