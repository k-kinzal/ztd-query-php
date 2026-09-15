<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\Driver\PdoConnection;
use ZtdQuery\Adapter\Pdo\Driver\PdoStatement;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;

#[CoversClass(PdoConnection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdoException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdo::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(PdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\StatementExecution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\Bindings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\BufferedRow::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\DriverSessionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\PreparedQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ConnectionExecution::class)]
final class PdoConnectionTest extends TestCase
{
    public function testItIsTheConnectionZtdReadsAndWritesThrough(): void
    {
        $pdo = new PDO('sqlite::memory:');

        self::assertContains(ConnectionInterface::class, class_implements(new PdoConnection($pdo)));
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
