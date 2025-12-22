<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\PdoStatement;
use ZtdQuery\Connection\StatementInterface;

#[CoversClass(PdoStatement::class)]
final class PdoStatementTest extends TestCase
{
    public function testImplementsStatementInterface(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $nativeStmt = $pdo->query('SELECT * FROM t');
        self::assertNotFalse($nativeStmt);

        $stmt = new PdoStatement($nativeStmt);

        self::assertInstanceOf(StatementInterface::class, $stmt);
    }

    public function testFetchAllReturnsAssociativeArrays(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO t VALUES (1, 'a')");
        $pdo->exec("INSERT INTO t VALUES (2, 'b')");

        $nativeStmt = $pdo->query('SELECT * FROM t ORDER BY id');
        self::assertNotFalse($nativeStmt);

        $stmt = new PdoStatement($nativeStmt);
        $rows = $stmt->fetchAll();

        self::assertCount(2, $rows);
        self::assertSame(1, $rows[0]['id']);
        self::assertSame('a', $rows[0]['name']);
    }

    public function testRowCountReturnsAffectedRows(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $pdo->exec('INSERT INTO t VALUES (1)');
        $pdo->exec('INSERT INTO t VALUES (2)');

        $nativeStmt = $pdo->prepare('DELETE FROM t');
        self::assertNotFalse($nativeStmt);
        $nativeStmt->execute();

        $stmt = new PdoStatement($nativeStmt);

        self::assertSame(2, $stmt->rowCount());
    }

    public function testExecuteReturnsTrueOnSuccess(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');

        $nativeStmt = $pdo->prepare('INSERT INTO t VALUES (1)');
        self::assertNotFalse($nativeStmt);

        $stmt = new PdoStatement($nativeStmt);

        self::assertTrue($stmt->execute());
    }
}
