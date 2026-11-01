<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\Magnitudes;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

/**
 * The SRID attribute of a spatial column: the spatial reference system every value of the column must use (MySQL 8.0.3 and later).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/spatial-type-overview.html.
 *
 * @visibility public
 * @example Reading the attribute of a column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (g POINT SRID 4326)');
 *     $create->elements[0]->specification->attributes[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\SridAttribute // => true
 */
final class SridAttribute implements ColumnAttribute
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Numeral $srid The spatial reference system identifier
     */
    public function __construct(public readonly Numeral $srid, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Reports an invalid integer token or a value beyond the column SRID range.
     * Availability of the referenced system belongs to the database catalog.
     */
    public function deriveAttribute(Derivation $derivation, Environment $scope): void
    {
        $problem = $this->problem();
        if ($problem !== null) {
            $derivation->report($problem);
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true), $this);
        }
    }

    /**
     * Returns the integer-token or column-range condition of the retained operand.
     */
    public function problem(): \SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber|\SqlSemantics\Platform\MySql\Statement\Table\Problem\Column\SridRange|null
    {
        $magnitudes = new Magnitudes();
        if (!$magnitudes->integral($this->srid)) {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber($this->srid);
        }
        return $magnitudes->atMost($this->srid, '4294967295') ? null : new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Column\SridRange($this->srid);
    }

    /**
     * Writes the attribute.
     */
    public function render(Output $out): void
    {
        $out->keyword('SRID')->node($this->srid);
    }
}
