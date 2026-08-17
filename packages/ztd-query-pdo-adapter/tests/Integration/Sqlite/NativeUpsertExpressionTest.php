<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/** @requires extension pdo_sqlite */
#[CoversNothing]
#[Large]
final class NativeUpsertExpressionTest extends TestCase
{
    public function testDatabaseEvaluatesJsonUpsertExpression(): void
    {
        $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC];
        $native = new \PDO('sqlite::memory:', null, null, $options);
        $underlying = new \PDO('sqlite::memory:', null, null, $options);
        $native->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, meta TEXT)');
        $underlying->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, meta TEXT)');
        $ztdPdo = ZtdPdo::fromPdo($underlying);
        $seed = "INSERT INTO items VALUES (1, '{\"color\":\"red\"}')";
        $native->exec($seed);
        $ztdPdo->exec($seed);

        $sql = "INSERT INTO items VALUES (1, '{\"color\":\"purple\"}') ON CONFLICT(id) DO UPDATE SET meta = json_set(items.meta, '$.color', 'purple')";
        $native->exec($sql);
        $ztdPdo->exec($sql);

        $nativeStatement = $native->query('SELECT * FROM items');
        $ztdStatement = $ztdPdo->query('SELECT * FROM items');
        self::assertNotFalse($nativeStatement);
        self::assertNotFalse($ztdStatement);
        self::assertSame($nativeStatement->fetchAll(), $ztdStatement->fetchAll());
    }
}
