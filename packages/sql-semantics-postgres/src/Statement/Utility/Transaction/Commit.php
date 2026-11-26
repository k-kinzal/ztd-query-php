<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;

/**
 * A request to commit the current transaction.
 *
 * Rule: PG-TRANSACTION-002. Mirrors PostgreSQL's `TransactionStmt` of kind
 * `TRANS_STMT_COMMIT` with its `chain` flag. WORK or TRANSACTION after
 * the command has no effect and is not kept. Facts: none; a transaction is
 * the state of a session.
 * Source: https://www.postgresql.org/docs/17/sql-commit.html, https://www.postgresql.org/docs/17/sql-end.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Commit implements Statement
{
    use Snapshot;

    /**
     * @param CommitSpelling $spelling The spelling of the command
     * @param Chaining|null $chaining AND CHAIN or AND NO CHAIN, when written
     */
    public function __construct(public readonly CommitSpelling $spelling = CommitSpelling::Commit, public readonly ?Chaining $chaining = null)
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
