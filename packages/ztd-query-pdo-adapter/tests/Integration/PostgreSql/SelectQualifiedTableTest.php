<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\PostgreSqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 */
#[CoversNothing]
#[Large]
final class SelectQualifiedTableTest extends TestCase
{
    public function testSchemaQualifiedTableReadsShadowRows(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();

        try {
            $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice')");

            $statement = $ztdPdo->query(sprintf('SELECT name FROM "%s".users WHERE id = 1', $schemaName));

            self::assertNotFalse($statement);
            self::assertSame([['name' => 'Alice']], $statement->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
