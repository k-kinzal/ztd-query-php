<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `SAVEPOINT name`: a request to set a named savepoint in the current transaction.
 *
 * Mirrors SQLCOM_SAVEPOINT. Rule: MYSQL-SAVEPOINT-001. The savepoint is a name of the
 * session's current transaction, which no declaration context holds; the
 * server compares savepoint names case-insensitively. A savepoint of the same name replaces the earlier one. The statement
 * names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/savepoint.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Naming the savepoint
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('savepoint sp1');
 *     [$statement->toString(), $statement->statement->savepoint->value] // => ['SAVEPOINT sp1', 'sp1']
 */
final class Savepoint implements Statement
{
    use Snapshot;

    /**
     * @param Name $savepoint The savepoint name
     */
    public function __construct(public readonly Name $savepoint)
    {
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
        $out->keyword('SAVEPOINT')->name($this->savepoint, NameUse::Identifier);
    }
}
