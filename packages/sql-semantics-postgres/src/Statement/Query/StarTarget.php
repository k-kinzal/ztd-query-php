<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Query\StarExpansion;

/**
 * A select-list `*`: every column of the FROM items.
 *
 * Mirrors PostgreSQL's `ResTarget` whose value is a bare `A_Star`. The
 * fields follow PG-STAR-001.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-SELECT-LIST.
 *
 * @visibility SqlSemantics
 */
final class StarTarget implements Target
{
    use Snapshot;

    /**
     * Expands the star over the FROM items of the level.
     */
    public function project(Derivation $derivation, Environment $environment, int $position): array
    {
        return (new StarExpansion())->all($derivation, $environment, $position);
    }

    /**
     * Writes the star.
     */
    public function render(Output $out): void
    {
        $out->symbol('*');
    }
}
