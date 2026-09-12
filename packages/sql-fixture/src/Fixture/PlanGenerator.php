<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use Faker\Generator;
use SqlFixture\FixtureGenerator;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Schema\SchemaResolverInterface;

/**
 * Generates the rows a plan describes, keeping related rows consistent.
 *
 * The walk starts at the table the plan is about and follows relations
 * outwards, once per connected group of tables. Starting anywhere else changes
 * the answer: a plan written `order.id < order_detail.order_id,
 * order.customer_id > customer.id` has customer as its only dependency-free
 * table, and beginning there would make order a collection hanging off it
 * rather than the one row the plan is about.
 *
 * Direction decides count. Walking to a child asks how many, walking to a
 * parent asks for the one row being referenced. Both carry the collection-ness
 * of where they came from, so a table reached through a list is a list however
 * many rows it happens to have.
 */
final class PlanGenerator
{
    /**
     * Initializes the collaborators and declared state for this object.
     */
    public function __construct(
        private readonly SchemaResolverInterface $schemas,
        private readonly FixtureGenerator $generator,
        private readonly Generator $faker,
    ) {
    }

    /**
     * @param array<string, int|array<mixed>|TableOverrides> $overrides Table name => what to override
     * @throws PlanSchemaException If the plan names a column the schema does not have
     */
    public function generate(FixturePlan $plan, array $overrides = []): FixtureSet
    {
        (new PlanSchemaValidator($this->schemas))->validate($plan);

        $run = new GenerationRun((new Generation\OverrideSpecs())->specs($overrides));

        foreach ($plan->tables as $table) {
            if ($run->hasVisited($table)) {
                continue;
            }

            $run->claim((new Generation\ConnectedTables())->connectedTo($plan, $table));
            (new Generation\RowMaterializer($this->schemas, $this->generator, $this->faker))->materialize($plan, $table, [], $run->specFor($table)->count ?? 1, false, null, $run);
        }

        return $run->toSet($plan);
    }






















}
