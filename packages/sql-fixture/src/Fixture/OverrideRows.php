<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

/**
 * Interprets the public override input as one column map or a list of row maps.
 */
final class OverrideRows
{
    /**
     * A list of arrays is one entry per row; anything else is one set of
     * column values that every row shares.
     *
     * @param array<mixed> $spec
     * @return list<array<mixed>>|null
     */
    public function asRows(array $spec): ?array
    {
        if (!array_is_list($spec)) {
            return null;
        }

        $rows = [];
        foreach ($spec as $entry) {
            if ($entry instanceof TableOverrides) {
                $rows[] = $entry->toArray();
                continue;
            }

            if (!is_array($entry)) {
                return null;
            }

            $rows[] = $entry;
        }

        return $rows;
    }

}
