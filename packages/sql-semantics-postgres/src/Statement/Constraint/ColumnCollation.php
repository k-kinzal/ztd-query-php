<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * The collation written among the constraints of a column or a domain.
 *
 * Mirrors the `CollateClause` that `ColQualList` collects into the column's
 * `collClause`. This clause carries the written name for the declaration rules to resolve.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html, https://www.postgresql.org/docs/17/collation.html.
 *
 * @visibility SqlSemantics
 */
final class ColumnCollation implements Clause
{
    use Snapshot;

    /**
     * @param DottedName $collation The collation name
     */
    public function __construct(public readonly DottedName $collation)
    {
    }

    /**
     * Derives nothing: the collation is a catalog name.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes COLLATE and the name.
     */
    public function render(Output $out): void
    {
        $out->keyword('COLLATE')->node($this->collation);
    }
}
