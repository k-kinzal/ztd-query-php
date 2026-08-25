<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use Faker\Generator;
use SqlFixture\Plan\Relation;

/**
 * Decides how many child rows a relation is generated with.
 *
 * A caller who said how many gets that many. Otherwise the relation itself
 * says the least it allows — none where the child was marked optional, one
 * otherwise — and how many more are useful is a judgement: a fixture of one
 * child row rarely exercises the code that reads a list, and a fixture of
 * hundreds is slow to build and hard to read in a failure.
 */
final class ChildRowCount
{
    private const SPREAD = 4;

    /**
     * @param Generator $faker Source of the choice where the caller did not make one
     */
    public function __construct(private readonly Generator $faker)
    {
    }

    /**
     * Answers how many rows of the child to generate.
     *
     * @param RowSpec $spec What the caller asked for on the child table
     * @param Relation $relation Relation being followed
     *
     * @return int How many rows to generate
     */
    public function of(RowSpec $spec, Relation $relation): int
    {
        if ($spec->count !== null) {
            return $spec->count;
        }

        $minimum = $relation->minimumChildRows();

        return $this->faker->numberBetween($minimum, $relation->maximumChildRows() ?? $minimum + self::SPREAD);
    }
}
