<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation\Joining;

use SqlSemantics\Model\Join;
use SqlSemantics\Model\TableUse;

/**
 * Lists the relation occurrences owned by a join tree, without entering aliased subqueries.
 *
 * @visibility SqlSemantics
 */
final class Inputs
{
    /**
     * @return list<TableUse>
     */
    public static function tables(TableUse|Join|null $input): array
    {
        return match (true) {
            $input === null => [],
            $input instanceof TableUse => [$input],
            $input instanceof Join => [...self::tables($input->left), ...self::tables($input->right)],
        };
    }
}
