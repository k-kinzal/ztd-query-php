<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `PARALLEL level`: whether the routine can run in parallel mode.
 *
 * The grammar reads any word; the server accepts `safe`, `restricted` and
 * `unsafe` only, which is checked when the statement is derived.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Telling an accepted level
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\ParallelSafety(new \SqlSemantics\Statement\Identifier\Name('safe')))->valid() // => true
 */
final class ParallelSafety implements RoutineOption
{
    use Snapshot;

    /**
     * @param Name $level The written level
     */
    public function __construct(public readonly Name $level)
    {
    }

    /**
     * Tells whether the server accepts the level.
     */
    public function valid(): bool
    {
        return in_array($this->level->value, ['safe', 'restricted', 'unsafe'], true);
    }

    /**
     * Tells that ALTER accepts the option.
     */
    public function alterable(): bool
    {
        return true;
    }

    /**
     * Answers `parallel`.
     */
    public function setting(): string
    {
        return 'parallel';
    }

    /**
     * Derives nothing: a word holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes PARALLEL and the level.
     */
    public function render(Output $out): void
    {
        $out->keyword('PARALLEL')->name($this->level, NameUse::Column);
    }
}
