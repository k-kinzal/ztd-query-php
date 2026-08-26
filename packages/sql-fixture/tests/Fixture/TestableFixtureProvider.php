<?php

declare(strict_types=1);

namespace Tests\Fixture;

use SqlFixture\FixtureProvider;
use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Schema\TableSchema;

/**
 * A provider that lets a test ask what a declaration was read as.
 *
 * Reading a declaration is something the provider does on the way to
 * generating a row, and what it read is not otherwise observable, so this
 * exposes it.
 */
final class TestableFixtureProvider extends FixtureProvider
{
    /**
     * Answers the table a declaration was read as.
     *
     * @param string $sql Declaration as it was written
     *
     * @return TableSchema The table it describes
     *
     * @throws SchemaParseException When the declaration cannot be read
     */
    public function exposeGetSchema(string $sql): TableSchema
    {
        return $this->getSchema($sql);
    }
}
