<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation;

use ReflectionObject;
use ReflectionReference;

/**
 * Reads immutable candidate state without invoking candidate behavior.
 * @visibility SqlSemantics
 */
final class ValueState
{
    /**
     * Rejects mutable/uninitialized properties and externally referenced array members.
     * @return list<object>|null
     */
    public function children(object $value): ?array
    {
        $reflection = new ReflectionObject($value);
        if (!$reflection->isFinal()) {
            return null;
        }
        $pending = [];
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isReadOnly() || !$property->isInitialized($value)) {
                return null;
            }
            $pending[] = $property->getValue($value);
        }
        $children = [];
        while ($pending !== []) {
            $member = array_pop($pending);
            if (is_object($member)) {
                $children[] = $member;
            } elseif (is_array($member)) {
                foreach ($member as $key => $item) {
                    if (ReflectionReference::fromArrayElement($member, $key) !== null) {
                        return null;
                    }
                    $pending[] = $item;
                }
            } elseif ($member !== null && !is_scalar($member)) {
                return null;
            }
        }
        return $children;
    }
}
