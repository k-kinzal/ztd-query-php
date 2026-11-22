<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;

/**
 * OF type_name: makes the table a typed table of a composite type.
 *
 * Mirrors `AT_AddOf`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
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
