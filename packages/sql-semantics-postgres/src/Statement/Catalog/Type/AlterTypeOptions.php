<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ChangeChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\AttributeChange;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\TypeChangeAttribute;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to change properties of a base type: `ALTER TYPE name SET ( property = value, ... )`.
 *
 * Rule: PG-TYPE-OPTIONS-001. Mirrors `AlterTypeStmt`; each property is read
 * as `AlterType` reads it (PG-ALTER-ATTRIBUTE-001), and a function property
 * set to NONE, or written alone, is removed. Source: https://www.postgresql.org/docs/17/sql-altertype.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the changed properties
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE t SET (storage = plain)');
 *     $operation->statement->options[0]->attribute->known // => \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\TypeChangeAttribute::Storage
 */
final class AlterTypeOptions implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<AttributeChange> The properties
     */
    public readonly array $options;

    /**
     * @param DottedName $name The type name
     * @param list<AttributeChange> $options The properties, read as ALTER TYPE reads them; at least one
     */
    public function __construct(public readonly DottedName $name, array $options)
    {
        $this->options = Check::listOf($options, AttributeChange::class, 'ALTER TYPE SET changes at least one property.', 1);
        foreach ($this->options as $change) {
            Check::input($change->attribute->known === null || $change->attribute->known instanceof TypeChangeAttribute, 'ALTER TYPE reads the properties it can change.');
        }
    }

    /**
     * Derives the property values and reports the properties the command refuses.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ClauseFacts())->derive($derivation, $this->options);
        (new ChangeChecks())->derive($derivation, $this->options, true);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TYPE')->node($this->name)->keyword('SET')->symbol('(')->list($this->options)->symbol(')');
    }
}
