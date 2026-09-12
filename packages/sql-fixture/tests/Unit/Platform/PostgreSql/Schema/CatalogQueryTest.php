<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\CatalogQuery as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class CatalogQueryTest extends TestCase
{
    public function testColumnsRetainsDatabaseOrdinalOrder(): void
    {
        $pdo = \Tests\Fixture\Database::postgres();
        $pdo->exec('CREATE TEMPORARY TABLE users (id INTEGER PRIMARY KEY, name VARCHAR(30) NOT NULL DEFAULT \'ready\')');
        $statement = $pdo->query('SELECT pg_my_temp_schema()::regnamespace::text');
        self::assertNotFalse($statement);
        $namespace = $statement->fetchColumn();
        self::assertIsString($namespace);
        $columns = (new Subject())->columns($pdo, $namespace, 'users');
        self::assertSame(['id', 'name'], array_column($columns, 'column_name'));
        self::assertSame('NO', $columns[1]['is_nullable']);
        self::assertSame('30', (string) $columns[1]['character_maximum_length']);
    }

    public function testPrimaryKeysReadsAQualifiedTemporarySchema(): void
    {
        $pdo = \Tests\Fixture\Database::postgres();
        $pdo->exec('CREATE TEMPORARY TABLE users (id INTEGER PRIMARY KEY, name VARCHAR(30) NOT NULL DEFAULT \'ready\')');
        $statement = $pdo->query('SELECT pg_my_temp_schema()::regnamespace::text');
        self::assertNotFalse($statement);
        $namespace = $statement->fetchColumn();
        self::assertIsString($namespace);
        self::assertSame(['id'], (new Subject())->primaryKeys($pdo, $namespace, 'users'));
    }
}
