<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to roll back the current transaction.
 *
 * Rule: PG-TRANSACTION-003. Mirrors PostgreSQL's `TransactionStmt` of kind
 * `TRANS_STMT_ROLLBACK` with its `chain` flag. WORK or TRANSACTION after
 * the command has no effect and is not kept. Facts: none; a transaction is
 * the state of a session.
 * Source: https://www.postgresql.org/docs/17/sql-rollback.html, https://www.postgresql.org/docs/17/sql-abort.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a chained end of a transaction
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ABORT AND CHAIN');
 *     [$operation->statement->chaining, $operation->toString()] // => [\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Chaining::Chain, 'ABORT AND CHAIN']
 */
final class Rollback implements Statement
{
    use Snapshot;

    /**
     * @param RollbackSpelling $spelling The spelling of the command
     * @param Chaining|null $chaining AND CHAIN or AND NO CHAIN, when written
     */
    public function __construct(public readonly RollbackSpelling $spelling = RollbackSpelling::Rollback, public readonly ?Chaining $chaining = null)
    {
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
        $out->keyword($this->spelling->value);
        if ($this->chaining !== null) {
            $out->keyword(...explode(' ', $this->chaining->value));
        }
    }
}
