<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_sqlite
 */
#[CoversNothing]
#[Large]
final class TransactionTest extends TestCase
{
    public function testRollbackRestoresInsertUpdateAndDeleteTogether(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $rawPdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo = ZtdPdo::fromPdo($rawPdo);
        $pdo->exec("INSERT INTO items VALUES (1, 'one')");
        $pdo->exec("INSERT INTO items VALUES (2, 'two')");

        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO items VALUES (3, 'three')");
        $pdo->exec("UPDATE items SET name = 'changed' WHERE id = 1");
        $pdo->exec('DELETE FROM items WHERE id = 2');
        $pdo->rollBack();

        $statement = $pdo->query('SELECT * FROM items ORDER BY id');
        self::assertNotFalse($statement);
        self::assertSame([
            ['id' => 1, 'name' => 'one'],
            ['id' => 2, 'name' => 'two'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testRollbackToSavepointRestoresOnlyNestedMutations(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $rawPdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo = ZtdPdo::fromPdo($rawPdo);
        $pdo->exec("INSERT INTO items VALUES (1, 'one')");

        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO items VALUES (2, 'two')");
        $pdo->exec('SAVEPOINT nested');
        $pdo->exec("INSERT INTO items VALUES (3, 'three')");
        $pdo->exec('ROLLBACK TO SAVEPOINT nested');
        $pdo->exec('RELEASE SAVEPOINT nested');
        $pdo->commit();

        $statement = $pdo->query('SELECT * FROM items ORDER BY id');
        self::assertNotFalse($statement);
        self::assertSame([
            ['id' => 1, 'name' => 'one'],
            ['id' => 2, 'name' => 'two'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testSqlTransactionStatementsUseTheSameShadowSnapshots(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $rawPdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo = ZtdPdo::fromPdo($rawPdo);

        $pdo->exec('BEGIN');
        $pdo->exec("INSERT INTO items VALUES (1, 'one')");
        $pdo->exec('ROLLBACK');

        $statement = $pdo->query('SELECT * FROM items');
        self::assertNotFalse($statement);
        self::assertSame([], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testRollbackRestoresVirtualSchemaRegistry(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo = ZtdPdo::fromPdo($rawPdo);

        $pdo->beginTransaction();
        $pdo->exec('CREATE TABLE virtual_items (id INTEGER PRIMARY KEY)');
        $pdo->rollBack();
        $pdo->exec('CREATE TABLE virtual_items (id INTEGER PRIMARY KEY)');
        $pdo->exec('INSERT INTO virtual_items VALUES (1)');

        $statement = $pdo->query('SELECT * FROM virtual_items');
        self::assertNotFalse($statement);
        self::assertSame([['id' => 1]], $statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
