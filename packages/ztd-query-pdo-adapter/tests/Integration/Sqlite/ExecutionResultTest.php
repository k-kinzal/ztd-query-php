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
final class ExecutionResultTest extends TestCase
{
    public function testReturningProjectsInsertUpdateAndDeleteRows(): void
    {
        $reference = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $underlying = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $schema = 'CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, score INTEGER)';
        $reference->exec($schema);
        $underlying->exec($schema);
        $pdo = ZtdPdo::fromPdo($underlying);

        $insert = "INSERT INTO items (name, score) VALUES ('Alice', 90), ('Bob', 80) RETURNING id, name";
        $referenceInsert = $reference->query($insert);
        $ztdInsert = $pdo->query($insert);
        self::assertNotFalse($referenceInsert);
        self::assertNotFalse($ztdInsert);
        self::assertSame($referenceInsert->fetchAll(), $ztdInsert->fetchAll());

        $update = 'UPDATE items SET score = score + 5 WHERE id = 1 RETURNING id, name, score';
        $referenceUpdate = $reference->query($update);
        $ztdUpdate = $pdo->query($update);
        self::assertNotFalse($referenceUpdate);
        self::assertNotFalse($ztdUpdate);
        self::assertSame($referenceUpdate->fetchAll(), $ztdUpdate->fetchAll());

        $delete = 'DELETE FROM items WHERE id = 2 RETURNING *';
        $referenceDelete = $reference->query($delete);
        $ztdDelete = $pdo->query($delete);
        self::assertNotFalse($referenceDelete);
        self::assertNotFalse($ztdDelete);
        self::assertSame($referenceDelete->fetchAll(), $ztdDelete->fetchAll());
    }

    public function testPreparedReturningAndLastInsertIdTrackShadowIdentity(): void
    {
        $underlying = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $underlying->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo = ZtdPdo::fromPdo($underlying);
        $statement = $pdo->prepare('INSERT INTO items (name) VALUES (?) RETURNING id, name');
        self::assertNotFalse($statement);

        $statement->execute(['first']);
        self::assertSame([['id' => 1, 'name' => 'first']], $statement->fetchAll());
        self::assertSame('1', $pdo->lastInsertId());
        $statement->execute(['second']);
        self::assertSame(['id' => 2, 'name' => 'second'], $statement->fetch());
        self::assertSame('2', $pdo->lastInsertId());

        $pdo->exec("INSERT INTO items (id, name) VALUES (42, 'explicit')");
        self::assertSame('42', $pdo->lastInsertId());
    }

    public function testRowCountUsesNativeMatchedRowConvention(): void
    {
        $reference = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $underlying = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $schema = 'CREATE TABLE items (id INTEGER PRIMARY KEY, score INTEGER)';
        $reference->exec($schema);
        $underlying->exec($schema);
        $pdo = ZtdPdo::fromPdo($underlying);
        $reference->exec('INSERT INTO items VALUES (1, 10)');
        $pdo->exec('INSERT INTO items VALUES (1, 10)');

        self::assertSame(
            $reference->exec('UPDATE items SET score = 10 WHERE id = 1'),
            $pdo->exec('UPDATE items SET score = 10 WHERE id = 1'),
        );
        $referenceStatement = $reference->prepare('UPDATE items SET score = ? WHERE id = ?');
        $ztdStatement = $pdo->prepare('UPDATE items SET score = ? WHERE id = ?');
        self::assertNotFalse($referenceStatement);
        self::assertNotFalse($ztdStatement);
        $referenceStatement->execute([10, 1]);
        $ztdStatement->execute([10, 1]);
        self::assertSame($referenceStatement->rowCount(), $ztdStatement->rowCount());
    }
}
