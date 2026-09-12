<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Generation;

use Faker\Generator;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\Plan\Relation;

/**
 * Chooses child row counts within a relation cardinality.
 *
 * @visibility root
 */
final class RelationCounts
{
    private const DEFAULT_SPREAD = 4;

    /**
     * Draws counts from the same source as fixture generation.
     */
    public function __construct(private readonly Generator $faker)
    {
    }

    /**
     * Chooses a child count within the relation bounds unless explicitly overridden.
     */
    public function resolveCount(RowSpec $spec, Relation $relation): int
    {
        if ($spec->count !== null) {
            return $spec->count;
        }

        $minimum = $relation->minimumChildRows();
        $maximum = $relation->maximumChildRows() ?? $minimum + self::DEFAULT_SPREAD;

        return $this->faker->numberBetween($minimum, $maximum);
    }
}
