<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * OF type_name: makes the table a typed table of a composite type.
 *
 * Mirrors `AT_AddOf`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Making a table typed
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t OF person');
 *     $statement->statement->commands[0]->type->last()->value // => 'person'
 */
final class OfComposite implements AlterCommand
{
    use Snapshot;

    /**
     * @param DottedName $type The composite type
     */
    public function __construct(public readonly DottedName $type)
    {
    }

    /**
     * Derives nothing: the type is a catalog name.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes OF and the type.
     */
    public function render(Output $out): void
    {
        $out->keyword('OF')->node($this->type);
    }
}
