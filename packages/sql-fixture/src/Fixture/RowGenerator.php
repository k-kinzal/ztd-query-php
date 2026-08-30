<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use SqlFixture\Hydrator\HydrationException;
use SqlFixture\InvalidOverrideException;
use SqlFixture\Schema\TableSchema;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Builds one row against one table.
 *
 * Walking a plan needs rows, not the whole of the generator that makes them:
 * it hands a table and the columns the plan fixed, and takes back a row. Saying
 * only that here keeps the walk from reaching back to the facade a caller
 * assembles, and lets a caller hand it something else that builds rows.
 *
 * @phpstan-import-type FixtureRow from TypeMapperInterface
 */
interface RowGenerator
{
    /**
     * Builds one row for the table, taking the columns the caller fixed.
     *
     * @template T of object
     * @param TableSchema $schema Table to build a row for
     * @param array<array-key, mixed> $overrides Columns the caller fixes, instead of generating them
     * @param class-string<T>|null $className Class to hydrate the row into, or null for the row itself
     *
     * @return ($className is null ? FixtureRow : T) The row, or the object it was hydrated into
     *
     * @throws InvalidOverrideException When an override names a column the table cannot hold
     * @throws HydrationException When the row cannot be turned into the class named
     */
    public function generate(TableSchema $schema, array $overrides = [], ?string $className = null): array|object;
}
