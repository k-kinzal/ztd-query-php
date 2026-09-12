<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Validation;

use SqlFixture\Fixture\PlanSchemaException;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Schema\SchemaResolverInterface;

/**
 * Checks that relation columns exist in their table schema.
 *
 * @visibility root
 */
final class EndpointValidator
{
    /**
     * Rejects unknown or generated columns in a relation endpoint.
     * @throws PlanSchemaException
     */
    public function checkEndpoint(SchemaResolverInterface $schemas, ColumnRef $reference): void
    {
        $schema = $schemas->resolve($reference->table);

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
