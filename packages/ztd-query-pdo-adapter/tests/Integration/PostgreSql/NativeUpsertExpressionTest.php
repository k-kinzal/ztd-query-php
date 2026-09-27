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

/**
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 */
#[CoversNothing]
#[Large]
final class NativeUpsertExpressionTest extends TestCase
{
    public function testDatabaseEvaluatesJsonUpsertExpression(): void
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


        try {
            $rawPdo->exec('CREATE TABLE items (id INT PRIMARY KEY, meta JSONB)');
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $seed = "INSERT INTO items VALUES (1, '{\"color\":\"red\"}')";
            $rawPdo->exec($seed);
            $ztdPdo->exec($seed);

            $sql = "INSERT INTO items VALUES (1, '{\"color\":\"purple\"}') ON CONFLICT(id) DO UPDATE SET meta = jsonb_set(items.meta, '{color}', '\"purple\"')";
            $rawPdo->exec($sql);
            $ztdPdo->exec($sql);

            $rawStatement = $rawPdo->query('SELECT id, meta::text FROM items');
            $ztdStatement = $ztdPdo->query('SELECT id, meta::text FROM items');
            self::assertNotFalse($rawStatement);
            self::assertNotFalse($ztdStatement);
            self::assertSame($rawStatement->fetchAll(), $ztdStatement->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
