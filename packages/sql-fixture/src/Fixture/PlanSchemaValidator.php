<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Schema\SchemaNotFoundException;
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
     * Builds a validator that checks a plan against the tables it names.
     *
     * @param SchemaResolverInterface $schemas Answers what a table looks like
     */
    public function __construct(private readonly SchemaResolverInterface $schemas)
    {
    }

    /**
     * @throws PlanSchemaException If a relation names a column that is not there
     * @throws SchemaNotFoundException If a table is not there
     */
    public function validate(FixturePlan $plan): void
    {
        foreach ($plan->tables as $table) {
            $this->schemas->resolve($table);
        }

        foreach ($plan->relations as $relation) {
            $this->checkEndpoint($relation->left);
            $this->checkEndpoint($relation->right);
        }
    }

    /**
     * Refuses an endpoint the schema cannot honour.
     *
     * A column the table does not have would be generated into nothing, and a
     * generated column is filled by the server, so a plan that binds one is asking
     * for a value that will be thrown away.
     *
     * @param ColumnRef $reference Endpoint to check
     *
     * @throws PlanSchemaException When a column is missing, or is one the server fills in
     * @throws SchemaNotFoundException When nothing can resolve the table
     */
    public function checkEndpoint(ColumnRef $reference): void
    {
        $schema = $this->schemas->resolve($reference->table);

        foreach ($reference->columns as $column) {
            $definition = $schema->getColumn($column);

            if ($definition === null) {
                throw PlanSchemaException::unknownColumn($reference, $column, $schema);
            }

            if ($definition->generated) {
                throw PlanSchemaException::generatedColumn($reference, $column, $schema);
            }
        }
    }
}
