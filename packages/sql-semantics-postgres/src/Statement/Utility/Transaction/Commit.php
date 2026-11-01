<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Statement;

/**
 * A request to commit the current transaction.
 *
 * Rule: PG-TRANSACTION-002. Mirrors PostgreSQL's `TransactionStmt` of kind
 * `TRANS_STMT_COMMIT` with its `chain` flag. WORK or TRANSACTION after
 * the command has no effect and is not kept. Facts: none; a transaction is
 * the state of a session.
 * Source: https://www.postgresql.org/docs/17/sql-commit.html, https://www.postgresql.org/docs/17/sql-end.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a chained end of a transaction
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('END AND CHAIN');
 *     [$operation->chaining, $operation->toString()] // => [\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Chaining::Chain, 'END AND CHAIN']
 */
final class Commit extends \SqlSemantics\Statement\SqlStatement implements Statement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param CommitSpelling $spelling The spelling of the command
     * @param Chaining|null $chaining AND CHAIN or AND NO CHAIN, when written
     */
    public function __construct(public readonly CommitSpelling $spelling = CommitSpelling::Commit, public readonly ?Chaining $chaining = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?\SqlSemantics\Statement\StatementSemantics $statementInformation = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
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
