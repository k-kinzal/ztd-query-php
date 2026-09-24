<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\ResourceGroup;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An inclusive range of virtual CPU numbers a resource group may run on; a single CPU has equal bounds.
 * @visibility public
 * @example Reading a CPU range
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER RESOURCE GROUP batch VCPU 0-3, 6');
 *     array_map(static fn ($range) => [$range->first, $range->last], $statement->cpus) // => [[0, 3], [6, 6]]
 */
final class CpuRange
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly int $first, public readonly int $last)
    {
        if ($first < 0 || $last < $first) {
            throw new InvalidStructure('A CPU range requires nonnegative bounds with the first not after the last.');
        }
    }
}
