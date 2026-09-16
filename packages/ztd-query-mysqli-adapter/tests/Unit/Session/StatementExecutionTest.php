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
use ZtdQuery\Adapter\Mysqli\Session\StatementExecution;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;

#[CoversClass(StatementExecution::class)]
#[Large]
#[UsesClass(MysqliConnection::class)]
#[UsesClass(MysqliResultStatement::class)]
#[UsesClass(MysqliResultColumnExtractor::class)]
#[UsesClass(MysqliResultProcessor::class)]
#[UsesClass(ZtdMysqliException::class)]
#[UsesClass(ConnectionExecution::class)]
final class StatementExecutionTest extends TestCase
{
    public function testNativeReturnsThePreparedStatement(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $session = (new ConnectionExecution($native))->session();
            $plan = $session->rewrite('INSERT INTO items VALUES (7)');
            $statement = $native->prepare($plan->sql());
            self::assertInstanceOf(mysqli_stmt::class, $statement);
            $execution = new StatementExecution($statement, $session, $plan);

            self::assertSame($statement, $execution->native());
        } finally {
            $container->stop();
        }
    }

    public function testResultTracksSimulatedWrites(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $session = (new ConnectionExecution($native))->session();
            $plan = $session->rewrite('INSERT INTO items VALUES (7)');
            $statement = $native->prepare($plan->sql());
            self::assertInstanceOf(mysqli_stmt::class, $statement);
            $execution = new StatementExecution($statement, $session, $plan);

            self::assertNull($execution->result());
            self::assertTrue($execution->execute());
            self::assertNotNull($execution->result());
            self::assertSame(1, $execution->result()->rowCount());
        } finally {
            $container->stop();
        }
    }

    public function testAffectedRowsReturnsTheSimulatedCount(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $session = (new ConnectionExecution($native))->session();
            $plan = $session->rewrite('INSERT INTO items VALUES (7)');
            $statement = $native->prepare($plan->sql());
            self::assertInstanceOf(mysqli_stmt::class, $statement);
            $execution = new StatementExecution($statement, $session, $plan);

            self::assertTrue($execution->execute());
            self::assertSame(1, $execution->affectedRows());
        } finally {
            $container->stop();
        }
    }

    public function testExecuteDoesNotWriteThePhysicalTable(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $session = (new ConnectionExecution($native))->session();
            $plan = $session->rewrite('INSERT INTO items VALUES (7)');
            $statement = $native->prepare($plan->sql());
            self::assertInstanceOf(mysqli_stmt::class, $statement);
            $execution = new StatementExecution($statement, $session, $plan);

            self::assertTrue($execution->execute());
            $result = $native->query('SELECT COUNT(*) FROM items');
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame(['0'], $result->fetch_row());
        } finally {
            $container->stop();
        }
    }

    public function testGetResultReturnsTheBufferedSelect(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $session = (new ConnectionExecution($native))->session();
            $plan = $session->rewrite('SELECT 7 AS id');
            $statement = $native->prepare($plan->sql());
            self::assertInstanceOf(mysqli_stmt::class, $statement);
            $execution = new StatementExecution($statement, $session, $plan);

            self::assertTrue($execution->execute());
            $result = $execution->getResult();
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame([['id' => 7]], $result->fetch_all(MYSQLI_ASSOC));
        } finally {
            $container->stop();
        }
    }

    public function testFetchReturnsNullForAWritingStatement(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $session = (new ConnectionExecution($native))->session();
            $plan = $session->rewrite('INSERT INTO items VALUES (7)');
            $statement = $native->prepare($plan->sql());
            self::assertInstanceOf(mysqli_stmt::class, $statement);
            $execution = new StatementExecution($statement, $session, $plan);

            self::assertTrue($execution->execute());
            self::assertNull($execution->fetch());
        } finally {
            $container->stop();
        }
    }

    public function testResetDiscardsTheSimulatedResult(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $native = $container->getData(mysqli::class);
            $native->query('CREATE TABLE items (id INT PRIMARY KEY)');
            $session = (new ConnectionExecution($native))->session();
            $plan = $session->rewrite('INSERT INTO items VALUES (7)');
            $statement = $native->prepare($plan->sql());
            self::assertInstanceOf(mysqli_stmt::class, $statement);
            $execution = new StatementExecution($statement, $session, $plan);

            self::assertTrue($execution->execute());
            self::assertNotNull($execution->result());
            self::assertTrue($execution->reset());
            self::assertNull($execution->result());
        } finally {
            $container->stop();
        }
    }

}
