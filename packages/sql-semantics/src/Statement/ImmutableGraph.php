<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use ReflectionObject;
use UnitEnum;

/**
 * Describes whether a value graph consists entirely of final readonly objects.
 *
 * Enums and comments are immutable by construction and are not inspected.
 *
 * @visibility SqlSemantics
 */
final class ImmutableGraph
{
    /**
     * Checks object state without invoking a writer or inspecting SQL text.
     */
    public function containsOnlyImmutableValues(Element $root): bool
    {
        $seen = [];
        $pending = [$root];
        while ($pending !== []) {
            $value = array_pop($pending);
            if ($value instanceof UnitEnum || isset($seen[spl_object_id($value)])) {
                continue;
            }
            $seen[spl_object_id($value)] = true;
            $reflection = new ReflectionObject($value);
            if (!$reflection->isFinal()) {
                return false;
            }
            foreach ($reflection->getProperties() as $property) {
                if (!$property->isReadOnly() || !$property->isInitialized($value)) {
                    return false;
                }
                $child = $property->getValue($value);
                if ($child instanceof Element) {
                    $pending[] = $child;
                } elseif (!is_scalar($child) && $child !== null && !$child instanceof Comments) {
                    return false;
                }
            }
        }

        return true;
    }
}
