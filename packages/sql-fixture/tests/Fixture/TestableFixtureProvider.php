<?php

declare(strict_types=1);

namespace Tests\Fixture;

use SqlFixture\FixtureProvider;
use SqlFixture\Schema\TableSchema;

/**
 * Fixture DTO used to verify object hydration.
 */
final class TestableFixtureProvider extends FixtureProvider
{
    /**
     * Returns expose get schema.
     */
    public function exposeGetSchema(string $sql): TableSchema
    {
        return $this->getSchema($sql);
    }
}
