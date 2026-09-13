<?php

declare(strict_types=1);

namespace Tests\Unit\Reflection\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Reflection\Catalog\TableQueries::class)]
final class TableQueriesTest extends TestCase
{
    public function testColumnsQueriesTheCurrentSchemaAndEscapesTheTableName(): void
    {
        $connection = new \Tests\Fake\FakeConnection(defaultRows: [['column_name' => 'id']]);
        $statement = (new \ZtdQuery\Platform\Postgres\Reflection\Catalog\TableQueries($connection))->columns("O'Brien");
        self::assertNotFalse($statement);
        self::assertSame([['column_name' => 'id']], $statement->fetchAll());
        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('FROM information_schema.columns', $connection->queries[0]);
        self::assertStringContainsString("'O''Brien'", $connection->queries[0]);
        self::assertStringContainsString('current_schema()', $connection->queries[0]);
    }

    public function testPrimaryKeyQueriesTheCurrentSchemaAndEscapesTheTableName(): void
    {
        $connection = new \Tests\Fake\FakeConnection(defaultRows: [['column_name' => 'id']]);
        $statement = (new \ZtdQuery\Platform\Postgres\Reflection\Catalog\TableQueries($connection))->primaryKey("O'Brien");
        self::assertNotFalse($statement);
        self::assertSame([['column_name' => 'id']], $statement->fetchAll());
        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('tc.constraint_type = \'PRIMARY KEY\'', $connection->queries[0]);
        self::assertStringContainsString("'O''Brien'", $connection->queries[0]);
        self::assertStringContainsString('current_schema()', $connection->queries[0]);
    }

    public function testUniqueIndexesQueriesTheCurrentSchemaAndEscapesTheTableName(): void
    {
        $connection = new \Tests\Fake\FakeConnection(defaultRows: [['column_name' => 'id']]);
        $statement = (new \ZtdQuery\Platform\Postgres\Reflection\Catalog\TableQueries($connection))->uniqueIndexes("O'Brien");
        self::assertNotFalse($statement);
        self::assertSame([['column_name' => 'id']], $statement->fetchAll());
        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('AND NOT index_metadata.indisprimary', $connection->queries[0]);
        self::assertStringContainsString("'O''Brien'", $connection->queries[0]);
        self::assertStringContainsString('current_schema()', $connection->queries[0]);
    }

    public function testForeignKeysQueriesTheCurrentSchemaAndEscapesTheTableName(): void
    {
        $connection = new \Tests\Fake\FakeConnection(defaultRows: [['column_name' => 'id']]);
        $statement = (new \ZtdQuery\Platform\Postgres\Reflection\Catalog\TableQueries($connection))->foreignKeys("O'Brien");
        self::assertNotFalse($statement);
        self::assertSame([['column_name' => 'id']], $statement->fetchAll());
        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('pk.ordinal_position = fk.position_in_unique_constraint', $connection->queries[0]);
        self::assertStringContainsString("'O''Brien'", $connection->queries[0]);
        self::assertStringContainsString('current_schema()', $connection->queries[0]);
    }
}
