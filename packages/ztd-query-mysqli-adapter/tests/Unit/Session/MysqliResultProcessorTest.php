<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use Container\MySql80Container;
use Container\MySql84Container;
use mysqli;
use mysqli_result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultColumnExtractor;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultStatement;
use ZtdQuery\Adapter\Mysqli\Session\MysqliResultProcessor;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Platform\MySql\MySqlResultColumnTypeResolver;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Session;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(MysqliResultProcessor::class)]
#[UsesClass(MysqliResultStatement::class)]
#[UsesClass(MysqliResultColumnExtractor::class)]
#[UsesClass(ZtdMysqliException::class)]
#[Large]
final class MysqliResultProcessorTest extends TestCase
{
    public function testProcessCreatesAnEmptyWriteResult(): void
    {
        $store = new ShadowStore();
        $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
        $plan = new RewritePlan('DO 1', QueryKind::WRITE_SIMULATED, new InsertMutation('items'));
        $result = (new MysqliResultProcessor())->process($session, $plan, false, 0);
        self::assertTrue($result->isSuccess());
        self::assertSame(0, $result->rowCount());
        self::assertSame([], $result->fetchAll());
    }

    public function testProcessUsesNativeRowsForTheShadowMutation(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = new mysqli(str_replace('localhost', '127.0.0.1', $container->getHost()), 'root', 'root', 'test', $container->getMappedPort(3306));
            $connection->set_charset('utf8mb4');
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $result = $connection->query('SELECT 7 AS id');
            self::assertInstanceOf(mysqli_result::class, $result);
            $plan = new RewritePlan('SELECT 7 AS id', QueryKind::WRITE_SIMULATED, new InsertMutation('items'));
            $processed = (new MysqliResultProcessor())->process($session, $plan, $result, 1);
            self::assertTrue($processed->isSuccess());
            self::assertSame(1, $processed->rowCount());
            self::assertSame([['id' => '7']], $store->get('items'));
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testProcessPreservesTheOriginalDatabaseException(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = new mysqli(str_replace('localhost', '127.0.0.1', $container->getHost()), 'root', 'root', 'test', $container->getMappedPort(3306));
            $connection->set_charset('utf8mb4');
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $result = $connection->query('SELECT 7 AS id');
            self::assertInstanceOf(mysqli_result::class, $result);
            $mutation = self::createStub(ShadowMutation::class);
            $failure = new DatabaseException('result processing failed', 123, 123);
            $mutation->method('apply')->willThrowException($failure);
            $plan = new RewritePlan('SELECT 7 AS id', QueryKind::WRITE_SIMULATED, $mutation);
            try {
                (new MysqliResultProcessor())->process($session, $plan, $result, 1);
                self::fail('Expected the MySQLi exception.');
            } catch (ZtdMysqliException $exception) {
                self::assertSame($failure, $exception->getPrevious());
                self::assertSame('result processing failed', $exception->getMessage());
                self::assertSame(0, $exception->getCode());
            } finally {
                $connection->close();
            }
        } finally {
            $container->stop();
        }
    }

}
