<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use ReflectionObject;
use UnitEnum;

/**
 * Audits the semantic result independently of SQL reconstruction.
 * @visibility SqlSemantics
 */
final class SemanticGraph
{
    /**
     * Requires an operation whose reachable values are immutable and independent of syntax.
     */
    public function isSemanticOperation(object $root): bool
    {
        return $root instanceof Operation && $this->containsOnlyValues($root);
    }

    /**
     * Rejects grammar models, parser objects, mutable properties, and hidden foreign state.
     */
    public function containsOnlyValues(object $root): bool
    {
        $pending = [$root];
        $seen = [];
        while ($pending !== []) {
            $value = array_pop($pending);
            $id = spl_object_id($value);
            if ($value instanceof Element || !str_starts_with($value::class, 'SqlSemantics\\Statement\\')) {
                return false;
            }
            if ($value instanceof UnitEnum || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $reflection = new ReflectionObject($value);
            if (!$reflection->isFinal()) {
                return false;
            }
            foreach ($reflection->getProperties() as $property) {
                if (!$property->isReadOnly() || !$property->isInitialized($value)) {
                    return false;
                }
                $field = $property->getValue($value);
                foreach (is_array($field) ? $field : [$field] as $member) {
                    if (is_object($member)) {
                        $pending[] = $member;
                    } elseif ($member !== null && !is_scalar($member)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Preserves object sharing and declaration references in a reconstruction comparison.
     */
    public function fingerprint(Operation $operation): string
    {
        assert($this->isSemanticOperation($operation), 'An operation must retain only immutable semantic values.');
        return serialize($operation);
    }
}
