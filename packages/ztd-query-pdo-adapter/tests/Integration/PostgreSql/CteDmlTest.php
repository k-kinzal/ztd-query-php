<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use Container\Endpoint;
use Container\PostgreSql16Container;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

#[CoversNothing]
#[Large]
final class CteDmlTest extends TestCase
{
    public function testCteDefinitionsRemainVisibleToRewrittenInsertUpdateAndDelete(): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(PostgreSql16Container::class)->getData(Endpoint::class);
        /** @var PDO $rawPdo */
        $rawPdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $rawPdo->exec(sprintf('SET search_path TO "%s"', $schemaName));

        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, value TEXT)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            self::assertSame(2, $ztdPdo->exec("WITH source(id, value) AS (VALUES (1, 'one'), (2, 'two')) INSERT INTO {$table} SELECT * FROM source"));
            self::assertSame(1, $ztdPdo->exec("WITH chosen AS (SELECT id FROM {$table} WHERE value = 'two') UPDATE {$table} SET value = 'changed' WHERE id IN (SELECT id FROM chosen)"));
            self::assertSame(1, $ztdPdo->exec("WITH chosen AS (SELECT id FROM {$table} WHERE value = 'one') DELETE FROM {$table} WHERE id IN (SELECT id FROM chosen)"));

            $statement = $ztdPdo->query("SELECT * FROM {$table}");
            self::assertNotFalse($statement);
            self::assertSame([['id' => 2, 'value' => 'changed']], $statement->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
