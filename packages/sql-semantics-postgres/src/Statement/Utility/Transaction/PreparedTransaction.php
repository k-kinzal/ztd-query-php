<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Statement;

/**
 * `PREPARE TRANSACTION`, `COMMIT PREPARED` or `ROLLBACK PREPARED`: a step of a two-phase commit.
 *
 * Rule: PG-TRANSACTION-005. Mirrors PostgreSQL's `TransactionStmt` with its
 * `gid`, the identifier of the prepared transaction as a string. Facts:
 * none; prepared transactions are the state of the server.
 * Source: https://www.postgresql.org/docs/17/sql-prepare-transaction.html, https://www.postgresql.org/docs/17/sql-commit-prepared.html,
 * https://www.postgresql.org/docs/17/sql-rollback-prepared.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the identifier of a prepared transaction
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("PREPARE TRANSACTION 'tx-1'");
 *     [$operation->identifier->value, $operation->toString()] // => ['tx-1', "PREPARE TRANSACTION 'tx-1'"]
 */
final class PreparedTransaction extends \SqlSemantics\Statement\SqlStatement implements Statement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param PreparedAction $action The step
     * @param StringConstant $identifier The identifier of the prepared transaction
     */
    public function __construct(public readonly PreparedAction $action, public readonly StringConstant $identifier, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?\SqlSemantics\Statement\StatementSemantics $statementInformation = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
    }

    /**
     * Derives nothing: prepared transactions are the state of the server.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->action->value))->node($this->identifier);
    }
}
