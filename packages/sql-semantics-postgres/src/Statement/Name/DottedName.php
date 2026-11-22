<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Name;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;

/**
 * A name of one or more parts separated by dots, as `any_name` and `func_name` write it.
 *
 * The grammar does not limit the number of parts; how many an object kind
 * accepts is decided when the name is looked up, so the parts are kept as
 * written. Up to three parts read as a relation-style name.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS.
 *
 * @visibility SqlSemantics
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
