<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\DefineChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\RangeAttribute;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to define a range type: `CREATE TYPE name AS RANGE ( subtype = type, ... )`.
 *
 * Rule: PG-TYPE-RANGE-001. Mirrors `CreateRangeStmt`. The attributes are
 * read the way `DefineRange` reads them: `subtype` as a type, `canonical` and
 * `subtype_diff` as routine names, `subtype_opclass` as an operator class,
 * `collation` as a collation and `multirange_type_name` as the name of the
 * multirange type to create. PG-DEFINE-CHECK-001 reports a missing subtype
 * and PG-DEFINE-ATTRIBUTE-001 unknown, repeated and unreadable attributes.
 * Source: https://www.postgresql.org/docs/17/sql-createtype.html, https://www.postgresql.org/docs/17/rangetypes.html#RANGETYPES-DEFINING.
 * Status: Implemented.
 *
 * @visibility public
 * @example Defining a range type without its subtype
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TYPE floatrange AS RANGE (subtype_diff = float8mi)');
 *     $operation->facts->diagnostics[0]->message() // => 'type attribute "subtype" is required'
 */
final class CreateRange implements Statement
{
    use Snapshot;

    /**
     * @var list<Attribute> The attributes in written order
     */
    public readonly array $options;

    /**
     * @param DottedName $name The type name
     * @param list<Attribute> $options The attributes in written order, at least one
     */
    public function __construct(public readonly DottedName $name, array $options)
    {
        $this->options = Check::listOf($options, Attribute::class, 'Range type attributes are definition attributes.', 1);
        foreach ($this->options as $option) {
            Check::input($option->known === null ? RangeAttribute::tryFrom($option->name->value) === null : $option->known instanceof RangeAttribute, 'A range type attribute is recognized exactly when CREATE TYPE ... AS RANGE knows its name.');
        }
    }

    /**
     * Derives the attribute values and checks the attribute names.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ClauseFacts())->derive($derivation, $this->options);
        (new DefineChecks())->range($this->options, $derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'TYPE')->node($this->name)->keyword('AS', 'RANGE')->symbol('(')->list($this->options)->symbol(')');
    }
}
