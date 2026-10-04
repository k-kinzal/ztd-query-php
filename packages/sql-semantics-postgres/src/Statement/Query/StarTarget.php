<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Query\StarExpansion;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A select-list `*`: every column of the FROM items.
 *
 * Mirrors PostgreSQL's `ResTarget` whose value is a bare `A_Star`. The
 * fields follow PG-STAR-001.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-SELECT-LIST.
 *
 * @visibility public
 * @example Keeping an unexpandable star as a typed request
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT * FROM t');
 *     [$query->statement->targets[0] instanceof \SqlSemantics\Platform\PostgreSql\Statement\Query\StarTarget, $query->facts->output->projection[0] instanceof \SqlSemantics\Statement\Shape\OpenStar] // => [true, true]
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
