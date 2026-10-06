<?php

declare(strict_types=1);

namespace SqlSemantics\Validation;

use SqlSemantics\Lowering\Leaves;

/**
 * Checks that every operand leaf lowered from the source is part of the published structure.
 *
 * The check is by object identity against the values actually stored in the
 * statement, not by counting consumed tokens.
 *
 * @visibility SqlSemantics
 */
final class LeafEmbedding
{
    /**
     * Answers the first recorded leaf that is not among the reachable objects, or null when all are.
     *
     * @param list<object> $reachable Every object reachable from the statement
     */
    public function dropped(Leaves $leaves, array $reachable): ?object
    {
        $present = [];
        foreach ($reachable as $object) {
            $present[spl_object_id($object)] = $object;
        }
        foreach ($leaves->all() as $leaf) {
            if (($present[spl_object_id($leaf)] ?? null) !== $leaf) {
                return $leaf;
            }
        }

        return null;
    }
}
