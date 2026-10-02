<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Name;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A name of one or more parts separated by dots, as `any_name` and `func_name` write it.
 *
 * The grammar does not limit the number of parts; how many an object kind
 * accepts is decided when the name is looked up, so the parts are kept as
 * written. Up to three parts read as a relation-style name.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS.
 *
 * @visibility public
 * @example Reading a schema-qualified type name
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT pg_catalog.int4 '1'");
 *     $name = $query->statement->targets[0]->expression->type->designation->name;
 *     [$name->qualified()?->schema?->value, $name->last()->value] // => ['pg_catalog', 'int4']
 */
final class DottedName implements ObjectReference
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The parts from the outermost qualifier to the object name
     */
    public readonly array $parts;

    /**
     * @param list<Name> $parts The parts from the outermost qualifier to the object name; at least one
     */
    public function __construct(array $parts)
    {
        $this->parts = Check::listOf($parts, Name::class, 'A dotted name has at least one part.', 1);
    }

    /**
     * Answers the object name: the last part.
     */
    public function last(): Name
    {
        return $this->parts[count($this->parts) - 1];
    }

    /**
     * Answers the name as object, schema and catalog, or null when it has more than three parts.
     */
    public function qualified(): ?QualifiedName
    {
        return match (count($this->parts)) {
            1 => new QualifiedName($this->parts[0]),
            2 => new QualifiedName($this->parts[1], $this->parts[0]),
            3 => new QualifiedName($this->parts[2], $this->parts[1], $this->parts[0]),
            default => null,
        };
    }

    /**
     * Derives nothing: a name holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the parts separated by dots.
     */
    public function render(Output $out): void
    {
        (new Spelling())->dotted($out, $this->parts);
    }
}
