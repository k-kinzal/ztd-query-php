<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * The SRID attribute of a spatial column: the spatial reference system every value of the column must use (MySQL 8.0.3 and later).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/spatial-type-overview.html.
 *
 * @visibility public
 * @example Reading the attribute of a column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (g POINT SRID 4326)');
 *     $create->statement->elements[0]->specification->attributes[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\SridAttribute // => true
 */
final class SridAttribute implements ColumnAttribute
{
    use Snapshot;

    /**
     * @param Numeral $srid The spatial reference system identifier
     */
    public function __construct(public readonly Numeral $srid)
    {
    }

    /**
     * Derives nothing: the attribute holds no expression.
     */
    public function deriveAttribute(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the attribute.
     */
    public function render(Output $out): void
    {
        $out->keyword('SRID')->node($this->srid);
    }
}
