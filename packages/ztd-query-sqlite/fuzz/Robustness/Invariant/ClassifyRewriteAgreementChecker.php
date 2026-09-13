<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Sqlite\SqliteInMemoryAttachStatement;
use ZtdQuery\Platform\Sqlite\SqliteQueryGuard;
use ZtdQuery\Platform\Sqlite\SqliteReadOnlyDiagnosticStatement;
use ZtdQuery\Platform\Sqlite\SqliteRewriter;
use ZtdQuery\Rewrite\QueryKind;

/**
 * Checks classify rewrite agreement invariants.
 */
final class ClassifyRewriteAgreementChecker implements InvariantChecker
{
    private SqliteQueryGuard $guard;
    private SqliteRewriter $rewriter;

    /**
     * Binds the dependencies used by this operation.
     */
    public function __construct(SqliteQueryGuard $guard, SqliteRewriter $rewriter)
    {
        $this->guard = $guard;
        $this->rewriter = $rewriter;
    }

    /**
     * Returns check.
     */
    public function check(string $sql): ?InvariantViolation
    {
        $diagnostic = SqliteReadOnlyDiagnosticStatement::isSafe($sql);
        $inMemoryAttach = SqliteInMemoryAttachStatement::isSafe($sql);
        $protectedPassthrough = $diagnostic || $inMemoryAttach;
        $passthroughInvariant = $inMemoryAttach ? 'INV-L2-09' : 'INV-L2-06';

        $classifyResult = $this->guard->classify($sql);

        if ($protectedPassthrough && $classifyResult !== QueryKind::READ) {
            return new InvariantViolation($passthroughInvariant, 'safe passthrough was not classified as READ', $sql);
        }

        try {
            $plan = $this->rewriter->rewrite($sql);
        } catch (UnknownSchemaException $exception) {
            if ($protectedPassthrough) {
                return new InvariantViolation($passthroughInvariant, 'safe passthrough required schema metadata', $sql, ['exception' => $exception::class]);
            }

            return null;
        } catch (UnsupportedSqlException $exception) {
            if ($protectedPassthrough) {
                return new InvariantViolation($passthroughInvariant, 'safe passthrough was rejected', $sql, ['exception' => $exception::class]);
            }

            return null;

        }

        if ($protectedPassthrough && ($plan->kind() !== QueryKind::READ || $plan->sql() !== $sql)) {
            return new InvariantViolation($passthroughInvariant, 'safe passthrough was not preserved as an unchanged READ plan', $sql);
        }

        if ($classifyResult === null) {
            return new InvariantViolation('INV-L2-05', 'unclassified SQL unexpectedly produced a rewrite plan', $sql);
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
