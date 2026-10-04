<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\TypeChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to define a range type: `CREATE TYPE name AS RANGE ( subtype = type, ... )`.
 *
 * Rule: PG-TYPE-RANGE-001. Mirrors `CreateRangeStmt`. The attributes are
 * checked by PG-TYPE-CHECK-001: subtype is required and unknown or repeated
 * attributes are diagnostics.
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
     * @var list<Definition> The attributes
     */
    public readonly array $options;

    /**
     * @param DottedName $name The type name
     * @param list<Definition> $options The attributes
     */
    public function __construct(public readonly DottedName $name, array $options)
    {
        $this->options = Check::listOf($options, Definition::class, 'Range type attributes are definitions.', 1);
    }

    /**
     * Derives the attribute values and checks the attribute names.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ClauseFacts())->derive($derivation, $this->options);
        (new TypeChecks())->range($derivation, $this->options);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'TYPE')->node($this->name)->keyword('AS', 'RANGE')->symbol('(')->list($this->options)->symbol(')');
    }
}
