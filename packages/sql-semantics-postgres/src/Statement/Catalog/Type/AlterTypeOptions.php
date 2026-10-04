<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to change properties of a base type: `ALTER TYPE name SET ( property = value, ... )`.
 *
 * Rule: PG-TYPE-OPTIONS-001. Mirrors `AlterTypeStmt`; a property set to NONE
 * is removed. Source: https://www.postgresql.org/docs/17/sql-altertype.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the changed properties
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE t SET (storage = plain)');
 *     $operation->statement->options[0]->name->value // => 'storage'
 */
final class AlterTypeOptions implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<Definition> The properties
     */
    public readonly array $options;

    /**
     * @param DottedName $name The type name
     * @param list<Definition> $options The properties; at least one
     */
    public function __construct(public readonly DottedName $name, array $options)
    {
        $this->options = Check::listOf($options, Definition::class, 'ALTER TYPE SET changes at least one property.', 1);
    }

    /**
     * Derives the property values.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ClauseFacts())->derive($derivation, $this->options);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TYPE')->node($this->name)->keyword('SET')->symbol('(')->list($this->options)->symbol(')');
    }
}
