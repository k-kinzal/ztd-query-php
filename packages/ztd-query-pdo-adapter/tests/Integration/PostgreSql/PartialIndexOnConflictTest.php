<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use PDO;
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
final class PartialIndexOnConflictTest extends TestCase
{
    public function testPartialUniqueIndexUpsertsRemainInTheShadowStore(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'partial_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec(
                "CREATE TABLE {$table} (email TEXT NOT NULL, status TEXT NOT NULL, login_count INTEGER NOT NULL)",
            );
            $rawPdo->exec(
                "CREATE UNIQUE INDEX {$table}_active_email ON {$table} (email) WHERE status = 'active'",
            );
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            self::assertSame(1, $ztdPdo->exec(
                "INSERT INTO {$table} VALUES ('alice@example.com', 'active', 5)",
            ));

            $prepared = $ztdPdo->prepare(
                "INSERT INTO {$table} VALUES (\$1, \$2, \$3) "
                . "ON CONFLICT (email) WHERE status = 'active' "
                . "DO UPDATE SET login_count = {$table}.login_count + EXCLUDED.login_count",
            );
            self::assertInstanceOf(\PDOStatement::class, $prepared);
            self::assertTrue($prepared->execute(['alice@example.com', 'active', 1]));
            self::assertSame(1, $prepared->rowCount());

            self::assertSame(1, $ztdPdo->exec(
                "INSERT INTO {$table} VALUES ('alice@example.com', 'active', 4) "
                . "ON CONFLICT (email) WHERE status = 'active' "
                . "DO UPDATE SET login_count = GREATEST({$table}.login_count, EXCLUDED.login_count)",
            ));
            self::assertSame(0, $ztdPdo->exec(
                "INSERT INTO {$table} VALUES ('alice@example.com', 'active', 99) "
                . "ON CONFLICT (email) WHERE status = 'active' DO NOTHING",
            ));
            self::assertSame(1, $ztdPdo->exec(
                "INSERT INTO {$table} VALUES ('alice@example.com', 'inactive', 7) "
                . "ON CONFLICT (email) WHERE status = 'active' DO NOTHING",
            ));

            $rows = $ztdPdo->query("SELECT email, status, login_count FROM {$table} ORDER BY status");
            self::assertNotFalse($rows);
            self::assertSame([
                ['email' => 'alice@example.com', 'status' => 'active', 'login_count' => 6],
                ['email' => 'alice@example.com', 'status' => 'inactive', 'login_count' => 7],
            ], $rows->fetchAll(PDO::FETCH_ASSOC));

            $physical = $rawPdo->query("SELECT COUNT(*) FROM {$table}");
            self::assertNotFalse($physical);
            self::assertSame(0, (int) $physical->fetchColumn());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
