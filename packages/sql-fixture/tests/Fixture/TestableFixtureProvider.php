<?php

declare(strict_types=1);

namespace Tests\Fixture;

use SqlFixture\FixtureProvider;
use SqlFixture\Schema\TableSchema;

final class TestableFixtureProvider extends FixtureProvider
{
    public function exposeGetSchema(string $sql): TableSchema
    {
        return $this->getSchema($sql);
    }
}
