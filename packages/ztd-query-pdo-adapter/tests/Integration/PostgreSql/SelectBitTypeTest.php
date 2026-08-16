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
final class SelectBitTypeTest extends TestCase
{
    public function testBitStringsPreserveWidthAndOperators(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, perms BIT(8) NOT NULL)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO {$table} (id, perms) VALUES (1, B'11111111'), (2, B'00001111'), (3, B'00000001')");

            $statement = $ztdPdo->query("SELECT perms::TEXT AS perms FROM {$table} WHERE (perms & B'11110000') <> B'00000000' ORDER BY id");

            self::assertNotFalse($statement);
            self::assertSame([['perms' => '11111111']], $statement->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
