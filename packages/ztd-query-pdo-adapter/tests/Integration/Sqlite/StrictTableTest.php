<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

#[CoversNothing]
final class StrictTableTest extends TestCase
{
    public function testStrictTableSupportsPreparedAndLiteralDml(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE measurements (id INTEGER PRIMARY KEY, sensor TEXT NOT NULL, reading REAL NOT NULL, count INTEGER NOT NULL) STRICT');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $insert = $ztdPdo->prepare('INSERT INTO measurements VALUES (?, ?, ?, ?)');
        self::assertNotFalse($insert);
        self::assertTrue($insert->execute([1, 'temp', 23.5, 100]));
        self::assertTrue($insert->execute([2, 'humidity', 60.0, 50]));

        self::assertSame(1, $ztdPdo->exec('UPDATE measurements SET count = count + 1 WHERE id = 1'));
        $delete = $ztdPdo->prepare('DELETE FROM measurements WHERE reading > ?');
        self::assertNotFalse($delete);
        self::assertTrue($delete->execute([50]));

        $statement = $ztdPdo->query('SELECT * FROM measurements ORDER BY id');
        self::assertNotFalse($statement);
        self::assertSame([
            ['id' => 1, 'sensor' => 'temp', 'reading' => 23.5, 'count' => 101],
        ], $statement->fetchAll());
    }
}
