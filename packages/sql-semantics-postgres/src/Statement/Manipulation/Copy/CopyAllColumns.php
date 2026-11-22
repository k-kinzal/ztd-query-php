<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;

/**
 * The argument `*` of a COPY option: every column copied.
 *
 * Mirrors the `A_Star` argument of a COPY `DefElem`.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html.
 *
 * @visibility SqlSemantics
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
