<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to define an access method.
 *
 * Rule: PG-ACCESS-METHOD-001. Mirrors `CreateAmStmt`: name, kind and handler.
 * Source: https://www.postgresql.org/docs/17/sql-create-access-method.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the kind of a new access method
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ACCESS METHOD heap2 TYPE TABLE HANDLER heap_tableam_handler');
 *     $operation->statement->kind // => \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\AccessMethodKind::Table
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
