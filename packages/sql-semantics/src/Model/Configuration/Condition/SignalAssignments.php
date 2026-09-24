<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Condition;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates the SET list of SIGNAL and RESIGNAL: each condition item is assigned at most once.
 * @visibility SqlSemantics
 */
final class SignalAssignments
{
    /**
     * @param list<SignalAssignment> $assignments
     * @throws InvalidStructure
     */
    public static function validate(array $assignments): void
    {
        Collections::objects($assignments, SignalAssignment::class);
        $items = array_map(static fn (SignalAssignment $assignment): string => $assignment->item->value, $assignments);
        if (count(array_unique($items)) !== count($items)) {
            throw new InvalidStructure('SIGNAL and RESIGNAL assign each condition item at most once.');
        }
    }
}
