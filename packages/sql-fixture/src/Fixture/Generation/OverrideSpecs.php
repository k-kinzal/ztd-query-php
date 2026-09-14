<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Generation;

use SqlFixture\Fixture\RowSpec;
use SqlFixture\Fixture\TableOverrides;

/**
 * Converts per-table requests to row specifications.
 *
 */
final class OverrideSpecs
{
    /**
     * @param array<string, int|array<mixed>|TableOverrides> $overrides
     * @return array<string, RowSpec>
     */
    public function specs(array $overrides): array
    {
        $specs = [];
        foreach ($overrides as $table => $spec) {
            $specs[$table] = RowSpec::from($table, $spec);
        }

        return $specs;
    }
}
