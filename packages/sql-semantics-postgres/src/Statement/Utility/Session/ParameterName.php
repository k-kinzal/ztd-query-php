<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The name of a configuration parameter, such as `search_path` or `plpgsql.variable_conflict`.
 *
 * Mirrors the `name` of PostgreSQL's `VariableSetStmt`: the dotted parts in
 * order. A parameter of an extension is written with the extension's prefix.
 * Parameter names are compared without regard to case by the server, and an
 * unquoted name is folded to lower case when it is read.
 * Source: https://www.postgresql.org/docs/17/sql-set.html, https://www.postgresql.org/docs/17/runtime-config-custom.html.
 *
 * @visibility public
 * @example Reading the text of a dotted parameter name
 *     $name = new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterName([new \SqlSemantics\Statement\Identifier\Name('plpgsql'), new \SqlSemantics\Statement\Identifier\Name('variable_conflict')]);
 *     $name->text() // => 'plpgsql.variable_conflict'
 * @example Refusing a name without parts
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterName([]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ParameterName implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The dotted parts in order
     */
    public readonly array $parts;

    /**
     * @param list<Name> $parts The dotted parts in order; at least one
     */
    public function __construct(array $parts)
    {
        $this->parts = Check::listOf($parts, Name::class, 'A parameter name has at least one part.', 1);
    }

    /**
     * Answers the name as the server receives it: the parts joined by dots.
     */
    public function text(): string
    {
        $texts = [];
        foreach ($this->parts as $part) {
            $texts[] = $part->value;
        }

        return implode('.', $texts);
    }

    /**
     * Writes the parts separated by dots.
     */
    public function render(Output $out): void
    {
        (new Spelling())->columns($out, $this->parts);
    }
}
