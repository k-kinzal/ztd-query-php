<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use ReflectionObject;
use RuntimeException;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Semantic\Statement\Delete;
use SqlSemantics\Semantic\Statement\InsertRows;
use SqlSemantics\Semantic\Statement\InsertSelect;
use SqlSemantics\Semantic\Statement\Select;
use UnitEnum;

/**
 * Audits and fingerprints semantic data without consulting SQL or parser output.
 *
 * @visibility SqlSemantics
 */
final class ModelGraph
{
    /**
     * Returns a deterministic description including sharing and reference identity.
     *
     * @throws RuntimeException When the model retains syntax or mutable state
     */
    public function fingerprint(Select|InsertRows|InsertSelect|Delete $statement): string
    {
        $this->inspect($statement);
        return serialize($statement);
    }

    /**
     * Audits the reachable object graph without calling a writer.
     *
     * @throws RuntimeException When the graph is not immutable semantic data
     */
    public function inspect(object $root): void
    {
        if (!$this->isImmutable($root)) {
            throw new RuntimeException('A statement must consist of immutable semantic values, without retained parser syntax.');
        }
    }

    /**
     * Checks the immutable semantic graph used by statement assertions.
     */
    public function isImmutable(object $root): bool
    {
        $seen = [];
        $pending = [$root];
        while ($pending !== []) {
            $value = array_pop($pending);
            $id = spl_object_id($value);
            if ($value instanceof UnitEnum || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            if (!str_starts_with($value::class, 'SqlSemantics\\Semantic\\') && !$value instanceof TypeDescriptor) {
                return false;
            }
            $reflection = new ReflectionObject($value);
            if (!$reflection->isFinal()) {
                return false;
            }
            foreach ($reflection->getProperties() as $property) {
                if (!$property->isReadOnly() || !$property->isInitialized($value)) {
                    return false;
                }
                $child = $property->getValue($value);
                foreach (is_array($child) ? $child : [$child] as $item) {
                    if (is_object($item)) {
                        $pending[] = $item;
                    } elseif ($item !== null && !is_scalar($item)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
}
