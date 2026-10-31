<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Union;

use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Statement\Query;

/**
 * Reads the resolved output widths before allocating combined set fields.
 * A leading union takes its output positions from its first query operand.
 *
 * @visibility MySqlMemory
 */
final class Widths
{
    /**
     * Reports a resolved mismatch without expanding stars or binding names again.
     */
    public function differ(Query|LeadingUnion $left, Query $right, StatementSemantics $facts): bool
    {
        if ($left instanceof LeadingUnion) {
            return $this->differ($left->left, $right, $facts);
        }
        $one = $left->queryInformation()->shape;
        $two = $right->queryInformation()->shape;
        return $one->complete() && $two->complete() && count($one->slots) !== count($two->slots);
    }
}
