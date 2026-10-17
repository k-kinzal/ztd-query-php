<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\Magnitudes;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule;
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
     * Checks that the column restriction is an unsigned 32-bit identifier, allowing zero.
     *
     * Rule: MYSQL-COLUMN-SRID-001. Every written SRID is checked, including a restriction
     * a later attribute replaces. Verified through SQL on MySQL 8.4.7. Status: Implemented.
     */
    public function deriveAttribute(Derivation $derivation, Environment $scope): void
    {
        $magnitudes = new Magnitudes();
        if ($magnitudes->integer($derivation, $this->srid) && !$magnitudes->atMost($this->srid, '4294967295')) {
            $derivation->report(new SpatialProblem(SpatialRule::IdentifierOutOfRange));
        }
    }

    /**
     * Writes the attribute.
     */
    public function render(Output $out): void
    {
        $out->keyword('SRID')->node($this->srid);
    }
}
