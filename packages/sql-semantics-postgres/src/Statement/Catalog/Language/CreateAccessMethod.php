<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * A request to define an access method.
 *
 * Rule: PG-ACCESS-METHOD-001. Mirrors `CreateAmStmt`: name, kind and handler.
 * Source: https://www.postgresql.org/docs/17/sql-create-access-method.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateAccessMethod implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The access method name
     * @param AccessMethodKind $kind Whether it is an index or a table access method
     * @param DottedName $handler The handler function
     */
    public function __construct(public readonly Name $name, public readonly AccessMethodKind $kind, public readonly DottedName $handler)
    {
    }

    /**
     * Derives nothing: the handler function is not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'ACCESS', 'METHOD')->name($this->name, NameUse::Column)->keyword('TYPE', $this->kind->value, 'HANDLER')->node($this->handler);
    }
}
