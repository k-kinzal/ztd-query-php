<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use Faker\Generator;
use SqlFixture\FixtureGenerator;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Schema\SchemaNotFoundException;
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
 */
final class PlanGenerator
{
    /**
     * @param SchemaResolverInterface $schemas Answers what a table looks like
     * @param FixtureGenerator $generator Builds one row against a table
     * @param Generator $faker Source of the choices the plan leaves open
     */
    public function __construct(
        private readonly SchemaResolverInterface $schemas,
        private readonly FixtureGenerator $generator,
        private readonly Generator $faker,
    ) {
    }

    /**
     * Generates every row the plan describes.
     *
     * @param FixturePlan $plan Plan to generate
     * @param array<string, int|array<mixed>|TableOverrides> $overrides Table name => what to override
     *
     * @return FixtureSet The rows, indexed by the table they belong to
     *
     * @throws PlanSchemaException When the plan names a column the schema does not have
     * @throws SchemaNotFoundException When the plan names a table nothing can resolve
     */
    public function generate(FixturePlan $plan, array $overrides = []): FixtureSet
    {
        (new PlanSchemaValidator($this->schemas))->validate($plan);

        $run = new GenerationRun(RowSpec::forTables($overrides));
        $walk = new PlanWalk($plan, $run, $this->schemas, $this->generator, new ChildRowCount($this->faker));

        foreach ($plan->tables as $table) {
            if ($run->hasVisited($table)) {
                continue;
            }
            $run->claim($plan->connectedTo($table));
            $walk->materialize($table, [], $run->specFor($table)->count ?? 1, false, null);
        }

        return $run->toSet($plan);
    }
}
