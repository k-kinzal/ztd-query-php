<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Container\PostgreSqlContainer;
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
        $containerInstance = \Testcontainers\Testcontainers::run(PostgreSqlContainer::class);
        /** @var PDO $rawPdo */
        $rawPdo = $containerInstance->getData(PDO::class);

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $rawPdo->exec(sprintf('SET search_path TO "%s"', $schemaName));


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
