<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `SUPPORT function`: the planner support function of the routine.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Reading the support function
 *     $option = new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\SupportFunction(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('helper')]));
 *     $option->function->last()->value // => 'helper'
 */
final class SupportFunction implements RoutineOption
{
    use Snapshot;

    /**
     * @param DottedName $function The support function
     */
    public function __construct(public readonly DottedName $function)
    {
    }

    /**
     * Tells that ALTER accepts the option.
     */
    public function alterable(): bool
    {
        return true;
    }

    /**
     * Answers `support`.
     */
    public function setting(): string
    {
        return 'support';
    }

    /**
     * Derives nothing: a name holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes SUPPORT and the function name.
     */
    public function render(Output $out): void
    {
        $out->keyword('SUPPORT')->node($this->function);
    }
}
