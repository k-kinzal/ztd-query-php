<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The result column `*`: every column of every input relation.
 *
 * @visibility public
 * @example Reading the columns a star selects
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER, b TEXT)');
 *     $query = $semantics->analyze('SELECT * FROM t', [$table]);
 *     [$query->statement->columns[0] instanceof \SqlSemantics\Platform\Sqlite\Statement\Query\Star, count($query->fields())] // => [true, 2]
 */
final class Star implements Node
{
    use Snapshot;

    /**
     * Writes the star.
     */
    public function render(Output $out): void
    {
        $out->symbol('*');
    }
}
