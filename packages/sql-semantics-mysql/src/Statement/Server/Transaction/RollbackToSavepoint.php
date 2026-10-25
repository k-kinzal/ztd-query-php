<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `ROLLBACK [WORK] TO [SAVEPOINT] name`: a request to undo the changes made after a savepoint.
 *
 * Mirrors SQLCOM_ROLLBACK_TO_SAVEPOINT. Rule: MYSQL-ROLLBACK-TO-SAVEPOINT-001. The savepoint is a name of the
 * session's current transaction, which no declaration context holds; the
 * server compares savepoint names case-insensitively. WORK and SAVEPOINT are optional; their presence is retained. The statement
 * names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/savepoint.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Naming the savepoint
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('rollback work to sp1');
 *     [$statement->toString(), $statement->statement->savepoint->value] // => ['ROLLBACK WORK TO sp1', 'sp1']
 */
final class RollbackToSavepoint implements Statement
{
    use Snapshot;

    /**
     * @param Name $savepoint The savepoint name
     * @param bool $work Whether WORK is written
     * @param bool $savepointWritten Whether SAVEPOINT is written
     */
    public function __construct(public readonly Name $savepoint, public readonly bool $work = false, public readonly bool $savepointWritten = true)
    {
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Has nothing to derive: the request names no relation and no value.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ROLLBACK');
        if ($this->work) {
            $out->keyword('WORK');
        }
        $out->keyword('TO');
        if ($this->savepointWritten) {
            $out->keyword('SAVEPOINT');
        }
        $out->name($this->savepoint, NameUse::Identifier);
    }
}
