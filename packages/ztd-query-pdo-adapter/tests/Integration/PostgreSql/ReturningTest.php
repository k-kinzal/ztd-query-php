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
final class ReturningTest extends TestCase
{
    public function testReturningAndLastInsertIdMatchPostgresDml(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id SERIAL PRIMARY KEY, name TEXT, score INTEGER)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $insert = "INSERT INTO {$table} (name, score) VALUES ('Alice', 90) RETURNING id, name";
            $rawInsert = $rawPdo->query($insert);
            $ztdInsert = $ztdPdo->query($insert);
            self::assertNotFalse($rawInsert);
            self::assertNotFalse($ztdInsert);
            self::assertSame($rawInsert->fetchAll(), $ztdInsert->fetchAll());
            self::assertSame('1', $ztdPdo->lastInsertId());

            $update = "UPDATE {$table} SET score = 95 WHERE id = 1 RETURNING id, name, score";
            $rawUpdate = $rawPdo->query($update);
            $ztdUpdate = $ztdPdo->query($update);
            self::assertNotFalse($rawUpdate);
            self::assertNotFalse($ztdUpdate);
            self::assertSame($rawUpdate->fetchAll(), $ztdUpdate->fetchAll());

            $delete = "DELETE FROM {$table} WHERE id = 1 RETURNING *";
            $rawDelete = $rawPdo->query($delete);
            $ztdDelete = $ztdPdo->query($delete);
            self::assertNotFalse($rawDelete);
            self::assertNotFalse($ztdDelete);
            self::assertSame($rawDelete->fetchAll(), $ztdDelete->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
