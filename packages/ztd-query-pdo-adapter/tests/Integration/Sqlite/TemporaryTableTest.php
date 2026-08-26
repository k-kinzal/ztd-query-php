<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/** @requires extension pdo_sqlite */
#[CoversNothing]
#[Large]
final class TemporaryTableTest extends TestCase
{
    public function testDmlContinuesAcrossTemporaryTableLifecycle(): void
    {
        $rawPdo = new PDO('sqlite::memory:');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec('CREATE TABLE source (id INTEGER PRIMARY KEY, value TEXT)');
        $ztdPdo->exec("INSERT INTO source VALUES (1, 'a')");
        $ztdPdo->exec('CREATE TEMP TABLE staging (id INTEGER PRIMARY KEY, value TEXT)');
        $ztdPdo->exec('INSERT INTO staging SELECT * FROM source');
        $ztdPdo->exec("UPDATE staging SET value = 'b' WHERE id = 1");
        $ztdPdo->exec('DELETE FROM staging WHERE id = 1');
        $ztdPdo->exec("INSERT INTO staging VALUES (2, 'c')");
        $ztdPdo->exec('INSERT INTO source SELECT * FROM staging');

        $statement = $ztdPdo->query('SELECT * FROM source ORDER BY id');

        self::assertNotFalse($statement);
        self::assertSame(
            [['id' => 1, 'value' => 'a'], ['id' => 2, 'value' => 'c']],
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    public function testTemporaryTableCreatedBeforeWrappingIsReflected(): void
    {
        $rawPdo = new PDO('sqlite::memory:');
        $rawPdo->exec('CREATE TEMPORARY TABLE staging (id INTEGER PRIMARY KEY, value TEXT)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $ztdPdo->exec("INSERT INTO staging VALUES (1, 'a')");
        $ztdPdo->exec("UPDATE staging SET value = 'b' WHERE id = 1");
        $statement = $ztdPdo->query('SELECT * FROM staging');

        self::assertNotFalse($statement);
        self::assertSame([['id' => 1, 'value' => 'b']], $statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
