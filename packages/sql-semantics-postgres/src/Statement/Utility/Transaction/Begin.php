<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;

/**
 * `BEGIN` or `START TRANSACTION`: a request to start a transaction block.
 *
 * Rule: PG-TRANSACTION-001. Mirrors PostgreSQL's `TransactionStmt` of kind
 * `TRANS_STMT_BEGIN` or `TRANS_STMT_START` with its mode options in the
 * order written. WORK or TRANSACTION after BEGIN, and the commas between
 * modes, have no effect and are not kept. Facts: none; whether a block is
 * already open is the state of the session.
 * Source: https://www.postgresql.org/docs/17/sql-begin.html, https://www.postgresql.org/docs/17/sql-start-transaction.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Begin implements Statement
{
    use Snapshot;

    /**
     * @var list<TransactionMode> The characteristics in the order written
     */
    public readonly array $modes;

    /**
     * @param BeginSpelling $spelling The spelling of the command
     * @param list<TransactionMode> $modes The characteristics in the order written
     */
    public function __construct(public readonly BeginSpelling $spelling = BeginSpelling::Begin, array $modes = [])
    {
        $this->modes = Check::listOf($modes, TransactionMode::class, 'The modes of a transaction are transaction modes.');
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
        $out->keyword(...explode(' ', $this->spelling->value));
        foreach ($this->modes as $mode) {
            $out->keyword(...explode(' ', $mode->value));
        }
    }
}
