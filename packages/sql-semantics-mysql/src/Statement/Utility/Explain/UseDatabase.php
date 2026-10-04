<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Explain;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * USE: makes a database the current database of the session.
 *
 * Rule: MYSQL-USE-001. The statement is a request; it does not change the
 * context, so later statements analyzed with the same context still use the
 * current database the context names. Facts: none (databases are not part of
 * a context). Terminates: a leaf.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/use.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading USE
 *     $use = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('use `my db`');
 *     [$use->statement->database->value, $use->toString()] // => ['my db', 'USE `my db`']
 */
final class UseDatabase implements Statement
{
    use Snapshot;

    /**
     * @param Name $database The database
     */
    public function __construct(public readonly Name $database)
    {
    }

    /**
     * Derives nothing: the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('USE')->name($this->database, NameUse::Qualifier);
    }
}
