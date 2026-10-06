<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * The collation written among the constraints of a column or a domain.
 *
 * Mirrors the `CollateClause` that `ColQualList` collects into the column's
 * `collClause`. The collation is looked up by the server; a version 1 context
 * declares no collations.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html, https://www.postgresql.org/docs/17/collation.html.
 *
 * @visibility public
 * @example Reading the collation of a column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a text COLLATE "C")');
 *     $create->statement->definition->elements[0]->qualifiers[0]->collation->last()->value // => 'C'
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
