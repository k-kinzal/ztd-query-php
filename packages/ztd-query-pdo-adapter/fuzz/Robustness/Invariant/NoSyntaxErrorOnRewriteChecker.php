<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use PDO;
use PDOException;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\SqlRewriter;

final class NoSyntaxErrorOnRewriteChecker
{
    private MySqlQueryGuard $guard;
    private SqlRewriter $rewriter;
    private PDO $rawPdo;

    public function __construct(MySqlQueryGuard $guard, SqlRewriter $rewriter, PDO $rawPdo)
    {
        $this->guard = $guard;
        $this->rewriter = $rewriter;
        $this->rawPdo = $rawPdo;
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
            $this->rawPdo->prepare($rewrittenSql);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1064) {
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
        }

        return null;
    }
}
