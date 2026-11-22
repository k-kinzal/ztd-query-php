<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Option;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;

/**
 * `SET parameter ...` or `RESET parameter`: a configuration parameter set while the routine runs.
 *
 * The clause is the SET or RESET command the utility family models (any
 * statement is accepted as the clause by the constructor); the
 * routine applies it on entry and restores the parameter on exit. It may be
 * written several times.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html, https://www.postgresql.org/docs/17/sql-alterfunction.html.
 *
 * @visibility SqlSemantics
 * @example Telling that a configuration setting may be repeated
 *     $clause = new \SqlSemantics\Platform\PostgreSql\Statement\Object\Drop(\SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind::Schema, [new \SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('s'))]);
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\ConfigurationSetting($clause))->setting() // => null
 */
final class ConfigurationSetting implements RoutineOption
{
    use Snapshot;

    /**
     * @param Statement $clause The SET or RESET clause
     */
    public function __construct(public readonly Statement $clause)
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
     * Answers null: settings of several parameters may be written.
     */
    public function setting(): ?string
    {
        return null;
    }

    /**
     * Derives the clause on its own: it changes no declaration and returns no rows.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->inspected($this->clause);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->node($this->clause);
    }
}
