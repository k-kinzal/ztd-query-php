<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use mysqli;
use mysqli_result;
use mysqli_stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;
use Tests\Container\MySql80Container;
use Tests\Container\MySql84Container;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliConnection;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultColumnExtractor;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultStatement;
use ZtdQuery\Adapter\Mysqli\Session\ConnectionExecution;
use ZtdQuery\Adapter\Mysqli\Session\MysqliResultProcessor;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;

#[CoversClass(ConnectionExecution::class)]
#[Large]
#[UsesClass(MysqliConnection::class)]
#[UsesClass(MysqliResultStatement::class)]
#[UsesClass(MysqliResultColumnExtractor::class)]
#[UsesClass(MysqliResultProcessor::class)]
#[UsesClass(ZtdMysqliException::class)]
final class ConnectionExecutionTest extends TestCase
{
    public function testNativeReturnsTheProvidedConnection(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $execution = new ConnectionExecution($native);

            self::assertSame($native, $execution->native());
        } finally {
            $container->stop();
        }
    }

    public function testSessionKeepsSimulatedWritesOffTheNativeConnection(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $execution = new ConnectionExecution($native);

            self::assertSame(1, $execution->session()->execStatement('INSERT INTO items VALUES (7)'));
            $result = $native->query('SELECT COUNT(*) FROM items');
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame(['0'], $result->fetch_row());
        } finally {
            $container->stop();
        }
    }

    public function testSimulatedAffectedRowsStartsWithoutAnOverride(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $execution = new ConnectionExecution($native);

            self::assertNull($execution->simulatedAffectedRows());
        } finally {
            $container->stop();
        }
    }

    public function testPreparePreservesTheWrappingCallback(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $execution = new ConnectionExecution($native);

            $wrapped = false;
            $stmt = $execution->prepare('SELECT 7 AS id', static function (mysqli_stmt $statement) use (&$wrapped): mysqli_stmt {
                $wrapped = true;
                return $statement;
            });
            self::assertTrue($wrapped);
            self::assertInstanceOf(mysqli_stmt::class, $stmt);
            self::assertTrue($stmt->execute());
        } finally {
            $container->stop();
        }
    }

    public function testQueryPreservesFacadeDispatch(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $execution = new ConnectionExecution($native);

            $queries = [];
            self::assertFalse($execution->query('SELECT 7 AS id', MYSQLI_STORE_RESULT, static function (string $sql) use (&$queries): bool {
                $queries[] = $sql;
                return false;
            }));
            self::assertSame(['SELECT 7 AS id'], $queries);
        } finally {
            $container->stop();
        }
    }

    public function testRealQuerySynchronizesTransactionStatements(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $execution = new ConnectionExecution($native);

            self::assertTrue($execution->realQuery('BEGIN', $native->prepare(...)));
            self::assertSame(1, $execution->session()->execStatement('INSERT INTO items VALUES (7)'));
            self::assertTrue($execution->realQuery('ROLLBACK', $native->prepare(...)));
            $result = $native->query($execution->session()->rewrite('SELECT id FROM items')->sql());
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame([], $result->fetch_all(MYSQLI_ASSOC));
        } finally {
            $container->stop();
        }
    }

    public function testBeginTransactionCreatesAShadowRollbackScope(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $execution = new ConnectionExecution($native);

            self::assertTrue($execution->beginTransaction());
            self::assertSame(1, $execution->session()->execStatement('INSERT INTO items VALUES (7)'));
            self::assertTrue($execution->rollBack());
            $result = $native->query($execution->session()->rewrite('SELECT id FROM items')->sql());
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame([], $result->fetch_all(MYSQLI_ASSOC));
        } finally {
            $container->stop();
        }
    }

    public function testCommitRetainsShadowRowsAcrossRollback(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $execution = new ConnectionExecution($native);

            self::assertTrue($execution->beginTransaction());
            self::assertSame(1, $execution->session()->execStatement('INSERT INTO items VALUES (7)'));
            self::assertTrue($execution->commit());
            self::assertTrue($execution->beginTransaction());
            self::assertTrue($execution->rollBack());
            $result = $native->query($execution->session()->rewrite('SELECT id FROM items')->sql());
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame([['id' => '7']], $result->fetch_all(MYSQLI_ASSOC));
        } finally {
            $container->stop();
        }
    }

    public function testRollBackRestoresTheShadowSnapshot(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $execution = new ConnectionExecution($native);

            self::assertTrue($execution->beginTransaction());
            self::assertSame(1, $execution->session()->execStatement('INSERT INTO items VALUES (7)'));
            self::assertTrue($execution->rollBack());
            $result = $native->query($execution->session()->rewrite('SELECT id FROM items')->sql());
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame([], $result->fetch_all(MYSQLI_ASSOC));
        } finally {
            $container->stop();
        }
    }

    public function testAutocommitCommitsShadowRows(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $execution = new ConnectionExecution($native);

            self::assertTrue($execution->autocommit(false));
            self::assertSame(1, $execution->session()->execStatement('INSERT INTO items VALUES (7)'));
            self::assertTrue($execution->autocommit(true));
            self::assertTrue($execution->beginTransaction());
            self::assertTrue($execution->rollBack());
            $result = $native->query($execution->session()->rewrite('SELECT id FROM items')->sql());
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame([['id' => '7']], $result->fetch_all(MYSQLI_ASSOC));
        } finally {
            $container->stop();
        }
    }

    public function testReleaseSavepointRemovesTheNativeSavepoint(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $execution = new ConnectionExecution($native);

            self::assertTrue($execution->beginTransaction());
            self::assertTrue($execution->savepoint('one'));
            self::assertTrue($execution->releaseSavepoint('one'));
            self::assertTrue($execution->rollBack());
        } finally {
            $container->stop();
        }
    }

    public function testSavepointRestoresShadowRowsOnRollbackToSavepoint(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $execution = new ConnectionExecution($native);

            self::assertTrue($execution->beginTransaction());
            self::assertTrue($execution->savepoint('one'));
            self::assertSame(1, $execution->session()->execStatement('INSERT INTO items VALUES (7)'));
            self::assertTrue($execution->realQuery('ROLLBACK TO SAVEPOINT one', $native->prepare(...)));
            $result = $native->query($execution->session()->rewrite('SELECT id FROM items')->sql());
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame([], $result->fetch_all(MYSQLI_ASSOC));
            self::assertTrue($execution->rollBack());
        } finally {
            $container->stop();
        }
    }

    public function testExecuteQueryRetainsTheDispatchedAffectedRowCount(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $execution = new ConnectionExecution($native);

            $result = $execution->executeQuery('SELECT ? AS id', [7], $native->prepare(...), static fn (): int => 7);
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame([['id' => '7']], $result->fetch_all(MYSQLI_ASSOC));
            self::assertSame(7, $execution->simulatedAffectedRows());
        } finally {
            $container->stop();
        }
    }

    public function testAffectedRowsUsesTheNativeCountBeforeSimulation(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $execution = new ConnectionExecution($native);

            $native->query('INSERT INTO items VALUES (1), (2)');
            self::assertSame(2, $execution->affectedRows());
        } finally {
            $container->stop();
        }
    }

}
