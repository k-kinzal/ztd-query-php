<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;

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
 *     $query->statement->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Dual // => true
 */
final class Dual implements \SqlSemantics\Statement\Relation
{
    use Snapshot;

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
}
