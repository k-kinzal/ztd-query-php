<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `SAVEPOINT name`, `RELEASE [SAVEPOINT] name` or `ROLLBACK TO [SAVEPOINT] name`: a request about a savepoint of the current transaction.
 *
 * Rule: PG-TRANSACTION-004. Mirrors PostgreSQL's `TransactionStmt` with its
 * `savepoint_name`. The word SAVEPOINT after RELEASE or ROLLBACK TO, and WORK
 * or TRANSACTION after ROLLBACK, are optional and have no effect; they are
 * not kept. Facts: none; savepoints are the state of a session.
 * Source: https://www.postgresql.org/docs/17/sql-savepoint.html, https://www.postgresql.org/docs/17/sql-release-savepoint.html,
 * https://www.postgresql.org/docs/17/sql-rollback-to.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a release of a savepoint
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('RELEASE SAVEPOINT s');
 *     [$operation->statement->name->value, $operation->toString()] // => ['s', 'RELEASE s']
 */
final class SavepointCommand implements Statement
{
    use Snapshot;

    /**
     * @param SavepointAction $action What is done with the savepoint
     * @param Name $name The savepoint name
     */
    public function __construct(public readonly SavepointAction $action, public readonly Name $name)
    {
    }

    /**
     * Derives nothing: savepoints are the state of a session.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        match ($this->action) {
            SavepointAction::Define => $out->keyword('SAVEPOINT'),
            SavepointAction::Release => $out->keyword('RELEASE'),
            SavepointAction::RollbackTo => $out->keyword('ROLLBACK', 'TO'),
        };
        $out->name($this->name, NameUse::Column);
    }
}
