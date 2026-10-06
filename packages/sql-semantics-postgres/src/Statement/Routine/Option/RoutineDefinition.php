<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `AS 'definition'` or `AS 'object_file', 'link_symbol'`: the routine's definition as unanalysed text of its language.
 *
 * The source is the text with the language it is written in, which the
 * server passes to that language without parsing it as SQL
 * (PG-ROUTINE-SOURCE-001); for a C function it names the shared library and
 * the symbol follows it.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Reading a string body
 *     $option = new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineDefinition(new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineSource(new \SqlSemantics\Statement\Identifier\Name('sql'), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('SELECT 1')));
 *     [$option->source->language?->value, $option->source->text->value, $option->symbol] // => ['sql', 'SELECT 1', null]
 */
final class RoutineDefinition implements RoutineOption
{
    use Snapshot;

    /**
     * @param RoutineSource $source The definition in its language, or the object file of a C function
     * @param StringConstant|null $symbol The link symbol of a C function, if written
     */
    public function __construct(public readonly RoutineSource $source, public readonly ?StringConstant $symbol = null)
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
     * Derives nothing: unanalysed text holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes AS and the strings.
     */
    public function render(Output $out): void
    {
        $out->keyword('AS')->node($this->source);
        if ($this->symbol !== null) {
            $out->symbol(',')->node($this->symbol);
        }
    }
}
