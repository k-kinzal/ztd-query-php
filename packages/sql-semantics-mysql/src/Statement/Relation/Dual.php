<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * `FROM DUAL`: the dummy table of one row and no columns.
 *
 * Rule: MYSQL-DUAL-001. The shape is complete and empty; no name refers to
 * it. Source: https://dev.mysql.com/doc/refman/8.4/en/select.html ("DUAL is
 * purely for the convenience of people who require that all SELECT
 * statements should have FROM"). Status: Implemented.
 *
 * @visibility public
 * @example Reading a selection from DUAL
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 FROM DUAL');
 *     $query->query->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Dual // => true
 */
final class Dual implements \SqlSemantics\Statement\Relation, \SqlSemantics\Statement\TableUse
{
    use \SqlSemantics\Statement\Semantics\RelationSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Answers the empty row.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return new RelationFact(new RowShape([]));
    }

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword('DUAL');
    }
    /**
     * Constructs this SQL form with optional completed semantics and an input range.
     */
    public function __construct(?RelationFact $relationInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($relationInformation !== null) {
            $this->initializeRelationInformation($relationInformation);
        }
    }
}
