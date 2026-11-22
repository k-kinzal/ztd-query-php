<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\TypeChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypedColumn;

/**
 * A request to define a composite type: `CREATE TYPE name AS ( attribute type, ... )`.
 *
 * Rule: PG-TYPE-COMPOSITE-001. Mirrors `CompositeTypeStmt`. A composite
 * type is a row type, not a relation that queries read, so the statement
 * provides no declaration. Attribute names must be distinct.
 * Source: https://www.postgresql.org/docs/17/sql-createtype.html#id-1.9.3.94.5.8. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateComposite implements Statement
{
    use Snapshot;

    /**
     * @var list<TypedColumn> The attributes in order
     */
    public readonly array $attributes;

    /**
     * @param DottedName $name The type name
     * @param list<TypedColumn> $attributes The attributes in order
     */
    public function __construct(public readonly DottedName $name, array $attributes)
    {
        $this->attributes = Check::listOf($attributes, TypedColumn::class, 'Composite type attributes are typed columns.');
    }

    /**
     * Derives the attribute types and reports repeated attribute names.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ClauseFacts())->derive($derivation, $this->attributes);
        (new TypeChecks())->attributes($derivation, $this->attributes);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'TYPE')->node($this->name)->keyword('AS')->symbol('(')->list($this->attributes)->symbol(')');
    }
}
