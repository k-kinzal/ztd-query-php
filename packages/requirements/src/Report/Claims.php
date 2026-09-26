<?php

declare(strict_types=1);

namespace Requirements\Report;

use Requirements\Model\Item;

/**
 * Records which specifications account for each source unit.
 *
 * A specification claims the units of its own evidence and of the requirements it refines,
 * unless its evidence or that of one of those requirements is invalid.
 */
final class Claims
{
    /**
     * Records the claims on the units and orders the units by key.
     *
     * @param array<string, SourceUnit> $units The collected units by key
     * @param array<string, Item> $items Every item by ID
     * @param array<string, list<string>> $evidence The quoted unit keys by item ID
     * @param array<string, true> $invalid The IDs of items with invalid evidence
     *
     * @return array<string, SourceUnit> The units ordered by key
     */
    public function assign(array $units, array $items, array $evidence, array $invalid): array
    {
        foreach ($items as $item) {
            $ids = [$item->id, ...$item->requirements];
            if ($item->kind !== 'specification' || array_intersect($ids, array_keys($invalid)) !== []) {
                continue;
            }
            foreach ($ids as $id) {
                foreach ($evidence[$id] as $key) {
                    $units[$key]->claims[$item->id] = $item;
                }
            }
        }
        ksort($units);
        return $units;
    }
}
