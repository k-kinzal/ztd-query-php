<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

/**
 * A routine option written as keywords only: null-input behavior, volatility, security, leakproofness or WINDOW.
 *
 * `CALLED ON NULL INPUT`, `RETURNS NULL ON NULL INPUT` and `STRICT` set the
 * same attribute; the last two mean the same and are kept apart because they
 * are written differently. `EXTERNAL SECURITY ...` is `SECURITY ...` with an
 * optional noise word. `WINDOW` is accepted by CREATE only.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Reading the attribute a keyword sets
 *     \SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineAttribute::ReturnsNullOnNullInput->setting() // => 'strict'
 */
enum RoutineAttribute: string implements RoutineOption
{
    case CalledOnNullInput = 'CALLED ON NULL INPUT';
    case ReturnsNullOnNullInput = 'RETURNS NULL ON NULL INPUT';
    case Strict = 'STRICT';
    case Immutable = 'IMMUTABLE';
    case Stable = 'STABLE';
    case Volatile = 'VOLATILE';
    case SecurityDefiner = 'SECURITY DEFINER';
    case SecurityInvoker = 'SECURITY INVOKER';
    case Leakproof = 'LEAKPROOF';
    case NotLeakproof = 'NOT LEAKPROOF';
    case Window = 'WINDOW';

    /**
     * Tells whether ALTER accepts the keyword: every one but WINDOW.
     */
    public function alterable(): bool
    {
        return $this !== self::Window;
    }

    /**
     * Answers the attribute the keyword sets.
     */
    public function setting(): string
    {
        return match ($this) {
            self::CalledOnNullInput, self::ReturnsNullOnNullInput, self::Strict => 'strict',
            self::Immutable, self::Stable, self::Volatile => 'volatility',
            self::SecurityDefiner, self::SecurityInvoker => 'security',
            self::Leakproof, self::NotLeakproof => 'leakproof',
            self::Window => 'window',
        };
    }

    /**
     * Derives nothing: a keyword holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keywords.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->value));
    }
}
