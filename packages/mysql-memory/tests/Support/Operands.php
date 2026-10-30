<?php

declare(strict_types=1);

namespace Tests\Support;

use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregate;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate;
use SqlSemantics\Statement\Query;

/**
 * Structural questions about generated operands.
 */
final class Operands
{
    /**
     * Tells whether an operand holds no aggregate or window call outside a subquery.
     *
     * The server refuses such a call where no query block groups rows: in a PURGE moment, a KILL
     * operand, a DO expression or the WHERE condition of a SHOW statement.
     */
    public static function aggregateFree(mixed $node): bool
    {
        $pending = [$node];
        $seen = new \SplObjectStorage();
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
                continue;
            }
            if (!is_object($value) || isset($seen[$value]) || $value instanceof Query || $value instanceof \UnitEnum) {
                continue;
            }
            $seen[$value] = true;
            if ($value instanceof SetFunction || $value instanceof WindowFunction || $value instanceof DeferredAggregate || $value instanceof DeferredWindowAggregate) {
                return false;
            }
            array_push($pending, ...array_values(get_object_vars($value)));
        }

        return true;
    }
}
