<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\Driver\PdoConnection;
use ZtdQuery\Adapter\Pdo\Session\ConnectionExecution;
use ZtdQuery\Adapter\Pdo\Session\DriverSessionFactory;
use ZtdQuery\Adapter\Pdo\Session\PreparedQuery;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;

#[CoversClass(ConnectionExecution::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(DriverSessionFactory::class)]
#[UsesClass(PreparedQuery::class)]
#[UsesClass(ZtdPdoException::class)]
#[Medium]
final class ConnectionExecutionTest extends TestCase
{
    public function testQueryPreservesPreparationDispatchAndFetchArguments(): void
    {
        $native = new PDO('sqlite::memory:');
        $execution = new ConnectionExecution($native);
        $queries = [];
        $statement = $execution->query('SELECT 7 AS id', PDO::FETCH_COLUMN, [1], static function (string $query) use ($native, &$queries): PDOStatement|false {
            $queries[] = $query;
            return $native->prepare("SELECT 9 AS id, 'Ada' AS name");
        });
        self::assertSame(['SELECT 7 AS id'], $queries);
        self::assertNotFalse($statement);
        self::assertSame(['Ada'], $statement->fetchAll());
    }

    public function testExecBatchStopsWhenTheDispatchedNativeStatementFails(): void
    {
        $native = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $execution = new ConnectionExecution($native);
        $queries = [];
        $result = $execution->exec('INSERT INTO users VALUES (1); INSERT INTO missing VALUES (2); INSERT INTO users VALUES (3)', static function (string $query) use ($native, &$queries): int|false {
            $queries[] = $query;
            return $native->exec($query);
        });
        self::assertFalse($result);
        self::assertCount(2, $queries);
        $statement = $native->query('SELECT id FROM users');
        self::assertNotFalse($statement);
        self::assertSame([1], $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testPreparePreservesOpaqueNativeDriverOptions(): void
    {
        $native = new PDO('sqlite::memory:');
        $execution = new ConnectionExecution($native);
        $statement = $execution->prepare('SELECT 7 AS id', [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY], static fn (PDOStatement $prepared): PDOStatement => $prepared);
        self::assertNotFalse($statement);
        self::assertTrue($statement->execute());
        self::assertSame(7, $statement->fetchColumn());
    }

    public function testNativeTransactionStatementsKeepTheSessionSynchronized(): void
    {
        $native = new PDO('sqlite::memory:');
        $execution = new ConnectionExecution($native);
        self::assertSame($native, $execution->native());
        self::assertSame(0, $execution->exec('BEGIN', $native->exec(...)));
        self::assertSame(0, $execution->exec('ROLLBACK', $native->exec(...)));
        self::assertFalse($native->inTransaction());
        self::assertTrue($execution->beginTransaction());
        self::assertTrue($execution->commit());
        self::assertFalse($native->inTransaction());
    }

    public function testSessionKeepsShadowWritesOffTheNativeConnection(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $execution = new ConnectionExecution($native);
        self::assertSame(1, $execution->session()->execStatement('INSERT INTO users VALUES (9)'));
        $physical = $native->query('SELECT COUNT(*) FROM users');
        $shadow = $native->query($execution->session()->rewrite('SELECT COUNT(*) FROM users')->sql());
        self::assertNotFalse($physical);
        self::assertNotFalse($shadow);
        self::assertSame(0, $physical->fetchColumn());
        self::assertSame(1, $shadow->fetchColumn());
    }

    public function testBeginTransactionPropagatesNativeFailure(): void
    {
        $native = new PDO('sqlite::memory:');
        $execution = new ConnectionExecution($native);
        $native->beginTransaction();
        $this->expectException(PDOException::class);
        $execution->beginTransaction();
    }

    public function testCommitKeepsShadowWritesAcrossTheNextRollback(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $execution = new ConnectionExecution($native);
        self::assertTrue($execution->beginTransaction());
        self::assertSame(1, $execution->session()->execStatement('INSERT INTO users VALUES (9)'));
        self::assertTrue($execution->commit());
        self::assertTrue($execution->beginTransaction());
        self::assertTrue($execution->rollBack());
        $statement = $native->query($execution->session()->rewrite('SELECT id FROM users')->sql());
        self::assertNotFalse($statement);
        self::assertSame([9], $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testRollBackRemovesUncommittedShadowWrites(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $execution = new ConnectionExecution($native);
        self::assertTrue($execution->beginTransaction());
        self::assertSame(1, $execution->session()->execStatement('INSERT INTO users VALUES (9)'));
        self::assertTrue($execution->rollBack());
        $statement = $native->query($execution->session()->rewrite('SELECT id FROM users')->sql());
        self::assertNotFalse($statement);
        self::assertSame([], $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testLastInsertIdChoosesShadowOrNativeState(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $native->exec('INSERT INTO users VALUES (7)');
        $execution = new ConnectionExecution($native);
        self::assertSame('7', $execution->lastInsertId());
        self::assertSame(1, $execution->session()->execStatement('INSERT INTO users VALUES (9)'));
        self::assertSame('9', $execution->lastInsertId());
        self::assertSame('7', $execution->lastInsertId('id'));
        $execution->session()->disable();
        self::assertSame('7', $execution->lastInsertId());
    }
}
