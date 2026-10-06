<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * The argument `*` of a COPY option: every column copied.
 *
 * Mirrors the `A_Star` argument of a COPY `DefElem`.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html.
 *
 * @visibility public
 * @example Reading the star argument
 *     $copy = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('COPY t TO STDOUT (force_quote *)');
 *     $copy->statement->options[0]->argument instanceof \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyAllColumns // => true
 */
final class CopyAllColumns implements OptionArgument
{
    use Snapshot;

    /**
     * Derives nothing: the star holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the star.
     */
    public function render(Output $out): void
    {
        $out->symbol('*');
    }
}
