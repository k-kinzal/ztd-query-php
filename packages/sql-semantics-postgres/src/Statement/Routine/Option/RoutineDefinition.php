<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `AS 'definition'` or `AS 'object_file', 'link_symbol'`: the routine body as a string.
 *
 * The string is passed to the routine's language as written; for a C
 * function it names the shared library and the symbol. It is opaque text to
 * the server's parser, so it is kept as the decoded string.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Reading a string body
 *     $option = new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineDefinition(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('SELECT 1'));
 *     [$option->definition->value, $option->symbol] // => ['SELECT 1', null]
 */
final class RoutineDefinition implements RoutineOption
{
    use Snapshot;

    /**
     * @param StringConstant $definition The definition, or the object file of a C function
     * @param StringConstant|null $symbol The link symbol of a C function, if written
     */
    public function __construct(public readonly StringConstant $definition, public readonly ?StringConstant $symbol = null)
    {
    }

    /**
     * Tells that ALTER does not accept the option.
     */
    public function alterable(): bool
    {
        return false;
    }

    /**
     * Answers `as`.
     */
    public function setting(): string
    {
        return 'as';
    }

    /**
     * Derives nothing: a string holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes AS and the strings.
     */
    public function render(Output $out): void
    {
        $out->keyword('AS')->node($this->definition);
        if ($this->symbol !== null) {
            $out->symbol(',')->node($this->symbol);
        }
    }
}
