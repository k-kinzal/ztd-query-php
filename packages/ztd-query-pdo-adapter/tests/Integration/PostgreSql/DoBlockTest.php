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
final class DoBlockTest extends TestCase
{
    public function testDoBlockPassesThroughAndLaterShadowDmlStillWorks(): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(PostgreSql16Container::class)->getData(Endpoint::class);
        /** @var PDO $pdo */
        $pdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $pdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $pdo->exec(sprintf('SET search_path TO "%s"', $schemaName));


        try {
            $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
            $ztdPdo = ZtdPdo::fromPdo($pdo);

            self::assertSame(0, $ztdPdo->exec(
                "DO \$block\$ BEGIN INSERT INTO items VALUES (1, 'physical'); END \$block\$",
            ));
            self::assertSame(1, $ztdPdo->exec("INSERT INTO items VALUES (2, 'shadow')"));

            $shadow = $ztdPdo->query('SELECT id, name FROM items ORDER BY id');
            self::assertNotFalse($shadow);
            self::assertSame([['id' => 2, 'name' => 'shadow']], $shadow->fetchAll());

            $physical = $pdo->query('SELECT id, name FROM items ORDER BY id');
            self::assertNotFalse($physical);
            self::assertSame([['id' => 1, 'name' => 'physical']], $physical->fetchAll());
        } finally {
            $pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
