<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\PdoConnection;
use ZtdQuery\Adapter\Pdo\PdoStatement;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;

#[CoversClass(PdoConnection::class)]
#[UsesClass(PdoStatement::class)]
final class PdoConnectionTest extends TestCase
{
    public function testImplementsConnectionInterface(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $connection = new PdoConnection($pdo);

        self::assertInstanceOf(ConnectionInterface::class, $connection);
    }

    public function testQueryReturnsStatementOnSuccess(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $connection = new PdoConnection($pdo);

        $result = $connection->query('SELECT * FROM t');

        self::assertInstanceOf(StatementInterface::class, $result);
    }

    public function testQueryReturnsFalseOnFailureInSilentMode(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $connection = new PdoConnection($pdo);

        $result = $connection->query('SELECT * FROM nonexistent_table');

        self::assertFalse($result);
    }

    public function testQueryThrowsDatabaseExceptionInExceptionMode(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection = new PdoConnection($pdo);

        $this->expectException(\ZtdQuery\Connection\Exception\DatabaseException::class);

        $connection->query('SELECT * FROM nonexistent_table');
    }

    public function testQueryThrowsDatabaseExceptionWithDriverErrorCode(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection = new PdoConnection($pdo);

        try {
            $connection->query('SELECT * FROM nonexistent_table');
            self::fail('Expected DatabaseException');
        } catch (\ZtdQuery\Connection\Exception\DatabaseException $e) {
            self::assertSame(1, $e->getDriverErrorCode());
        }
    }
}
