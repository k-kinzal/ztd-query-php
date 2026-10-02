<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation;

use UnitEnum;

/**
 * Audits closed immutable state without calling methods on candidate values.
 * @visibility SqlSemantics
 */
final class ValueGraph
{
    /**
     * Rejects unregistered implementations before examining their state or behavior.
     */
    public function accepts(object $root): bool
    {
        $pending = [$root];
        $seen = [];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (!ValueDomain::contains($value)) {
                return false;
            }
            if ($value instanceof UnitEnum || isset($seen[spl_object_id($value)])) {
                continue;
            }
            $seen[spl_object_id($value)] = true;
            $children = (new ValueState())->children($value);
            if ($children === null) {
                return false;
            }
            array_push($pending, ...$children);
        }
        return true;
    }

}
