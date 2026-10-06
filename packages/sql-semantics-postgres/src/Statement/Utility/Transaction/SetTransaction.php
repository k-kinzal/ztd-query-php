<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `SET TRANSACTION modes` or `SET SESSION CHARACTERISTICS AS TRANSACTION modes`: a request to set transaction characteristics.
 *
 * Rule: PG-TRANSACTION-006. Mirrors PostgreSQL's `VariableSetStmt` of kind
 * `VAR_SET_MULTI` named `TRANSACTION` or `SESSION CHARACTERISTICS`, with the
 * modes in the order written. The commas between modes have no effect and
 * are not kept. Facts: none.
 * Source: https://www.postgresql.org/docs/17/sql-set-transaction.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the modes of SET TRANSACTION
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET TRANSACTION READ ONLY, DEFERRABLE');
 *     [$operation->statement->modes[1], $operation->toString()] // => [\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\TransactionMode::Deferrable, 'SET TRANSACTION READ ONLY DEFERRABLE']
 * @example Refusing a SET TRANSACTION without modes
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SetTransaction(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\TransactionScope::Transaction, []) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class SetTransaction implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<TransactionMode> The characteristics in the order written
     */
    public readonly array $modes;

    /**
     * @param TransactionScope $scope Which transactions the characteristics apply to
     * @param list<TransactionMode> $modes The characteristics in the order written; at least one
     * @param bool $local Whether SET LOCAL is written
     */
    public function __construct(public readonly TransactionScope $scope, array $modes, public readonly bool $local = false)
    {
        $this->modes = Check::listOf($modes, TransactionMode::class, 'SET TRANSACTION takes at least one mode.', 1);
    }

    /**
     * Derives nothing: a transaction is the state of a session.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('SET');
        if ($this->local) {
            $out->keyword('LOCAL');
        }
        $out->keyword(...explode(' ', $this->scope->value));
        foreach ($this->modes as $mode) {
            $out->keyword(...explode(' ', $mode->value));
        }
    }
}
