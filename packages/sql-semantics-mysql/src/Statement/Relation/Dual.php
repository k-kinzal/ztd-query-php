<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * `FROM DUAL`: the dummy table of one row and no columns.
 *
 * Rule: MYSQL-DUAL-001. The shape is complete and empty; no name refers to
 * it. Source: https://dev.mysql.com/doc/refman/8.4/en/select.html ("DUAL is
 * purely for the convenience of people who require that all SELECT
 * statements should have FROM"). Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Dual implements \SqlSemantics\Platform\MySql\Model\Statement\Relation
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
