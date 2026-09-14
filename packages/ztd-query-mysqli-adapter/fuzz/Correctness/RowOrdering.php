<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

/**
 * Orders result rows by the selected primary key columns.
 */
final class RowOrdering
{
    /**
     * Sort rows by primary key columns.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $keys
     * @return array<int, array<string, mixed>>
     */
    public function sortByKeys(array $rows, array $keys): array
    {
        usort($rows, function (array $a, array $b) use ($keys): int {
            foreach ($keys as $key) {
                $cmp = ($a[$key] ?? '') <=> ($b[$key] ?? '');
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
            return 0;
        });
        return $rows;
    }
}
