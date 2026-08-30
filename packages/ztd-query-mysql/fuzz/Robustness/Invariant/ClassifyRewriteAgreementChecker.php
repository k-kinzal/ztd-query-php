<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Rewrite\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\Rewrite\MySqlReadOnlyDiagnosticStatement;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\SqlRewriter;

/**
 * The classify rewrite agreement checker, as invariant checker.
 */
final class ClassifyRewriteAgreementChecker implements InvariantChecker
{
    private MySqlQueryGuard $guard;
    private SqlRewriter $rewriter;

    /**
     * Binds the instance to what it will work from.
     *
     * @param MySqlQueryGuard $guard
     * @param SqlRewriter $rewriter
     */
    public function __construct(MySqlQueryGuard $guard, SqlRewriter $rewriter)
    {
        $this->guard = $guard;
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
        $diagnostic = MySqlReadOnlyDiagnosticStatement::isSafe($sql);

        $classifyResult = $this->guard->classify($sql);

        if ($diagnostic && $classifyResult !== QueryKind::READ) {
            return new InvariantViolation('INV-L2-06', 'read-only diagnostic was not classified as READ', $sql);
        }

        if ($classifyResult === null) {
            try {
                $this->rewriter->rewrite($sql);
                return null;
            } catch (UnsupportedSqlException) {
                return null;
            }
        }

        try {
            $plan = $this->rewriter->rewrite($sql);
        } catch (UnknownSchemaException $exception) {
            if ($diagnostic) {
                return new InvariantViolation('INV-L2-06', 'read-only diagnostic required schema metadata', $sql, ['exception' => $exception::class]);
            }

            return null;
        } catch (UnsupportedSqlException $exception) {
            if ($diagnostic) {
                return new InvariantViolation('INV-L2-06', 'read-only diagnostic was rejected', $sql, ['exception' => $exception::class]);
            }

            return null;
        }

        if ($diagnostic && ($plan->kind() !== QueryKind::READ || $plan->sql() !== $sql)) {
            return new InvariantViolation('INV-L2-06', 'read-only diagnostic was not preserved as an unchanged READ plan', $sql);
        }

        if ($plan->kind() !== $classifyResult) {
            return new InvariantViolation(
                'INV-L2-05',
                'classify() and rewrite() disagree on QueryKind',
                $sql,
                [
                    'classify_result' => $classifyResult->value,
                    'rewrite_kind' => $plan->kind()->value,
                ]
            );
        }

        return null;
    }
}
