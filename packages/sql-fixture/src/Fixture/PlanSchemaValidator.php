<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use SqlFixture\Plan\FixturePlan;
use SqlFixture\Schema\SchemaResolverInterface;

/**
 * Checks a plan against the tables it names, before anything is generated.
 *
 * Without this a mistyped column is not an error but a quiet change of
 * meaning: the relation stops linking, and the child's foreign key is filled
 * with a generated value instead of its parent's key. The fixtures look
 * plausible and the test fails, or passes, for reasons that have nothing to do
 * with what it was written to check.
 */
final class PlanSchemaValidator
{
    /**
     * Initializes the collaborators and declared state for this object.
     */
    public function __construct(private readonly SchemaResolverInterface $schemas)
    {
    }

    /**
     * @throws PlanSchemaException If a relation names a column that is not there
     * @throws \SqlFixture\Schema\SchemaNotFoundException If a table is not there
     */
    public function validate(FixturePlan $plan): void
    {
        foreach ($plan->tables as $table) {
            $this->schemas->resolve($table);
        }

        foreach ($plan->relations as $relation) {
            (new Validation\EndpointValidator())->checkEndpoint($this->schemas, $relation->left);
            (new Validation\EndpointValidator())->checkEndpoint($this->schemas, $relation->right);
        }
    }


}
