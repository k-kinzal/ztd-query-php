<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\TableSchema;

#[CoversNothing]
final class SchemaFetcherInterfaceTest extends TestCase
{
    public function testFetchSchemaAnswersTheTableTheImplementationRead(): void
    {
        $schema = new TableSchema('users', ['id' => new ColumnDefinition('id', 'int')], ['id']);
        $fetcher = self::createStub(SchemaFetcherInterface::class);
        $fetcher->method('fetchSchema')->willReturn($schema);

        self::assertSame($schema, $fetcher->fetchSchema(new PDO('sqlite::memory:'), 'users'));
    }
}
