<?php

declare(strict_types=1);

namespace Tests\Unit;

use Containers\MySql80Container;
use Containers\MySql84Container;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use mysqli_stmt;
use mysqli_warning;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;
use ZtdQuery\Adapter\Mysqli\MysqliConnection;
use ZtdQuery\Adapter\Mysqli\MysqliResultColumnExtractor;
use ZtdQuery\Adapter\Mysqli\MysqliResultProcessor;
use ZtdQuery\Adapter\Mysqli\MysqliResultStatement;
use ZtdQuery\Adapter\Mysqli\MysqliStatementBindingBridge;
use ZtdQuery\Adapter\Mysqli\Native\MysqliPropertyReader;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliStatement;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlTransactionStatementParser;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ZtdMysqli::class)]
#[Large]
#[UsesClass(MysqliConnection::class)]
#[UsesClass(MysqliPropertyReader::class)]
#[UsesClass(ZtdMysqliStatement::class)]
#[UsesClass(ZtdMysqliException::class)]
#[UsesClass(MysqliStatementBindingBridge::class)]
#[UsesClass(MysqliResultProcessor::class)]
#[UsesClass(MysqliResultStatement::class)]
#[UsesClass(MysqliResultColumnExtractor::class)]
final class ZtdMysqliTest extends TestCase
{
    public function testFromMysqliPassesTheConnectionAndConfigurationToItsFactory(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $config = ZtdConfig::default();
        $rewriter = self::createStub(SqlRewriter::class);
        $factory = self::createMock(SessionFactory::class);
        $factory->expects(self::once())->method('create')->with(self::isInstanceOf(MysqliConnection::class), self::identicalTo($config))
            ->willReturnCallback(static fn (ConnectionInterface $native, ZtdConfig $resolved): Session => new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $resolved, $native));
        $ztd = ZtdMysqli::fromMysqli($connection, $config, $factory);
        self::assertTrue($ztd->isZtdEnabled());
        $connection->close();
    }

    public function testEnableZtdRestoresDisabledMode(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $ztd->disableZtd();
        self::assertFalse($ztd->isZtdEnabled());
        $ztd->enableZtd();
        self::assertTrue($ztd->isZtdEnabled());
        $connection->close();
    }

    public function testDisableZtdDisablesRewriting(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        self::assertTrue($ztd->isZtdEnabled());
        $ztd->disableZtd();
        self::assertFalse($ztd->isZtdEnabled());
        $connection->close();
    }

    public function testIsZtdEnabledDefaultsToTrue(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        self::assertTrue($ztd->isZtdEnabled());
        $connection->close();
    }

    public function testPrepareReturnsTheNativeStatementWhenDisabled(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $ztd->disableZtd();
        $statement = $ztd->prepare('SELECT 7 AS id');
        self::assertInstanceOf(mysqli_stmt::class, $statement);
        self::assertNotInstanceOf(ZtdMysqliStatement::class, $statement);
        self::assertTrue($statement->execute());
        $result = $statement->get_result();
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['id' => 7]], $result->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testPrepareReturnsASimulatedStatementWhenEnabled(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $statement = $ztd->prepare('SELECT 7 AS id');
        self::assertInstanceOf(ZtdMysqliStatement::class, $statement);
        self::assertTrue($statement->execute());
        $result = $statement->get_result();
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['id' => 7]], $result->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testPrepareWrapsRewriteFailures(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $store = new ShadowStore();
        $rewriter = self::createStub(SqlRewriter::class);
        $rewriter->method('transactionStatement')->willReturnCallback((new MySqlTransactionStatementParser())->parse(...));
        $factory = self::createStub(SessionFactory::class);
        $factory->method('create')->willReturnCallback(static fn (ConnectionInterface $native, ZtdConfig $config): Session => new Session($rewriter, $store, new ResultSelectRunner(), $config, $native));
        $ztd = ZtdMysqli::fromMysqli($connection, null, $factory);
        $rewriter->method('rewrite')->willThrowException(new UnsupportedSqlException('DROP DATABASE forbidden', 'Unsupported'));
        try {
            $ztd->prepare('DROP DATABASE forbidden');
            self::fail('Expected write protection.');
        } catch (ZtdMysqliException $exception) {
            self::assertStringContainsString('ZTD Write Protection', $exception->getMessage());
            self::assertSame(0, $exception->getCode());
            self::assertNotNull($exception->getPrevious());
        } finally {
            $connection->close();
        }
    }

    public function testPrepareAndQueriesPreserveNativeFalseResults(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $store = new ShadowStore();
        $rewriter = self::createStub(SqlRewriter::class);
        $rewriter->method('transactionStatement')->willReturnCallback((new MySqlTransactionStatementParser())->parse(...));
        $factory = self::createStub(SessionFactory::class);
        $factory->method('create')->willReturnCallback(static fn (ConnectionInterface $native, ZtdConfig $config): Session => new Session($rewriter, $store, new ResultSelectRunner(), $config, $native));
        $ztd = ZtdMysqli::fromMysqli($connection, null, $factory);
        $rewriter->method('rewrite')->willReturn(new RewritePlan('SELECT missing_column', QueryKind::READ));
        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            self::assertFalse($ztd->prepare('SELECT missing_column'));
            self::assertFalse($ztd->query('SELECT missing_column'));
            self::assertFalse($ztd->real_query('SELECT missing_column'));
            self::assertFalse($ztd->execute_query('SELECT missing_column'));
        } finally {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $connection->close();
        }
    }

    public function testQueryReadsTheNativeResultWhenDisabled(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $ztd->disableZtd();
        $result = $ztd->query('SELECT 7 AS id', MYSQLI_USE_RESULT);
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['id' => '7']], $result->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testQueryReturnsFalseWhenThePreparedExecutionFails(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $store = new ShadowStore();
        $rewriter = self::createStub(SqlRewriter::class);
        $rewriter->method('transactionStatement')->willReturnCallback((new MySqlTransactionStatementParser())->parse(...));
        $factory = self::createStub(SessionFactory::class);
        $factory->method('create')->willReturnCallback(static fn (ConnectionInterface $native, ZtdConfig $config): Session => new Session($rewriter, $store, new ResultSelectRunner(), $config, $native));
        $ztd = ZtdMysqli::fromMysqli($connection, null, $factory);
        $connection->query('CREATE TEMPORARY TABLE duplicate_keys (id INT PRIMARY KEY)');
        $connection->query('INSERT INTO duplicate_keys VALUES (1)');
        $rewriter->method('rewrite')->willReturn(new RewritePlan('INSERT INTO duplicate_keys VALUES (1)', QueryKind::READ));
        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            self::assertFalse($ztd->query('INSERT INTO duplicate_keys VALUES (1)'));
            self::assertFalse($ztd->execute_query('INSERT INTO duplicate_keys VALUES (1)'));
        } finally {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $connection->close();
        }
    }

    public function testQueryReturnsTrueWithoutAResultSet(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $store = new ShadowStore();
        $rewriter = self::createStub(SqlRewriter::class);
        $rewriter->method('transactionStatement')->willReturnCallback((new MySqlTransactionStatementParser())->parse(...));
        $factory = self::createStub(SessionFactory::class);
        $factory->method('create')->willReturnCallback(static fn (ConnectionInterface $native, ZtdConfig $config): Session => new Session($rewriter, $store, new ResultSelectRunner(), $config, $native));
        $ztd = ZtdMysqli::fromMysqli($connection, null, $factory);
        $rewriter->method('rewrite')->willReturn(new RewritePlan('DO 1', QueryKind::READ));
        self::assertTrue($ztd->query('DO 1'));
        $connection->close();
    }

    public function testExecute_queryBindsParametersInBothModes(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $result = $ztd->execute_query('SELECT ? AS value', ['enabled']);
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['value' => 'enabled']], $result->fetch_all(MYSQLI_ASSOC));
        $ztd->disableZtd();
        $result = $ztd->execute_query('SELECT ? AS value', ['disabled']);
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['value' => 'disabled']], $result->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testLastAffectedRowsUsesTheNativeCountWhenDisabled(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $ztd->disableZtd();
        $ztd->query('CREATE TEMPORARY TABLE counts (id INT)');
        self::assertTrue($ztd->query('INSERT INTO counts VALUES (1), (2)'));
        self::assertSame(2, $ztd->lastAffectedRows());
        $connection->close();
    }

    public function testBegin_transactionCreatesAShadowRollbackScope(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $store = new ShadowStore();
        $rewriter = self::createStub(SqlRewriter::class);
        $rewriter->method('transactionStatement')->willReturnCallback((new MySqlTransactionStatementParser())->parse(...));
        $factory = self::createStub(SessionFactory::class);
        $factory->method('create')->willReturnCallback(static fn (ConnectionInterface $native, ZtdConfig $config): Session => new Session($rewriter, $store, new ResultSelectRunner(), $config, $native));
        $ztd = ZtdMysqli::fromMysqli($connection, null, $factory);
        $store->set('items', [['id' => 1]]);
        self::assertTrue($ztd->begin_transaction(MYSQLI_TRANS_START_READ_WRITE, 'scope'));
        $store->insert('items', [['id' => 2]]);
        self::assertTrue($ztd->rollback());
        self::assertSame([['id' => 1]], $store->get('items'));
        $connection->close();
    }

    public function testCommitRetainsTheShadowRows(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $store = new ShadowStore();
        $rewriter = self::createStub(SqlRewriter::class);
        $rewriter->method('transactionStatement')->willReturnCallback((new MySqlTransactionStatementParser())->parse(...));
        $factory = self::createStub(SessionFactory::class);
        $factory->method('create')->willReturnCallback(static fn (ConnectionInterface $native, ZtdConfig $config): Session => new Session($rewriter, $store, new ResultSelectRunner(), $config, $native));
        $ztd = ZtdMysqli::fromMysqli($connection, null, $factory);
        $store->set('items', [['id' => 1]]);
        $ztd->begin_transaction();
        $store->insert('items', [['id' => 2]]);
        self::assertTrue($ztd->commit(0, 'commit_scope'));
        $ztd->rollback();
        self::assertSame([['id' => 1], ['id' => 2]], $store->get('items'));
        $connection->close();
    }

    public function testRollbackRestoresTheShadowSnapshot(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $store = new ShadowStore();
        $rewriter = self::createStub(SqlRewriter::class);
        $rewriter->method('transactionStatement')->willReturnCallback((new MySqlTransactionStatementParser())->parse(...));
        $factory = self::createStub(SessionFactory::class);
        $factory->method('create')->willReturnCallback(static fn (ConnectionInterface $native, ZtdConfig $config): Session => new Session($rewriter, $store, new ResultSelectRunner(), $config, $native));
        $ztd = ZtdMysqli::fromMysqli($connection, null, $factory);
        $store->set('items', [['id' => 1]]);
        $ztd->begin_transaction();
        $store->insert('items', [['id' => 2]]);
        self::assertTrue($ztd->rollback(0, 'rollback_scope'));
        self::assertSame([['id' => 1]], $store->get('items'));
        $connection->close();
    }

    public function testAutocommitCommitsOrRollsBackTheShadowScope(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $store = new ShadowStore();
        $rewriter = self::createStub(SqlRewriter::class);
        $rewriter->method('transactionStatement')->willReturnCallback((new MySqlTransactionStatementParser())->parse(...));
        $factory = self::createStub(SessionFactory::class);
        $factory->method('create')->willReturnCallback(static fn (ConnectionInterface $native, ZtdConfig $config): Session => new Session($rewriter, $store, new ResultSelectRunner(), $config, $native));
        $ztd = ZtdMysqli::fromMysqli($connection, null, $factory);
        $store->set('items', [['id' => 1]]);
        self::assertTrue($ztd->autocommit(false));
        $store->insert('items', [['id' => 2]]);
        $ztd->rollback();
        self::assertSame([['id' => 1]], $store->get('items'));
        $store->insert('items', [['id' => 3]]);
        self::assertTrue($ztd->autocommit(true));
        $ztd->rollback();
        self::assertSame([['id' => 1], ['id' => 3]], $store->get('items'));
        $connection->close();
    }

    public function testReal_queryAppliesTransactionStatements(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $store = new ShadowStore();
        $rewriter = self::createStub(SqlRewriter::class);
        $rewriter->method('transactionStatement')->willReturnCallback((new MySqlTransactionStatementParser())->parse(...));
        $factory = self::createStub(SessionFactory::class);
        $factory->method('create')->willReturnCallback(static fn (ConnectionInterface $native, ZtdConfig $config): Session => new Session($rewriter, $store, new ResultSelectRunner(), $config, $native));
        $ztd = ZtdMysqli::fromMysqli($connection, null, $factory);
        $store->set('items', [['id' => 1]]);
        self::assertTrue($ztd->real_query('BEGIN'));
        $store->insert('items', [['id' => 2]]);
        self::assertTrue($ztd->real_query('ROLLBACK'));
        self::assertSame([['id' => 1]], $store->get('items'));
        $connection->close();
    }

    public function testSavepointPreservesItsShadowSnapshot(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $store = new ShadowStore();
        $rewriter = self::createStub(SqlRewriter::class);
        $rewriter->method('transactionStatement')->willReturnCallback((new MySqlTransactionStatementParser())->parse(...));
        $factory = self::createStub(SessionFactory::class);
        $factory->method('create')->willReturnCallback(static fn (ConnectionInterface $native, ZtdConfig $config): Session => new Session($rewriter, $store, new ResultSelectRunner(), $config, $native));
        $ztd = ZtdMysqli::fromMysqli($connection, null, $factory);
        $store->set('items', [['id' => 1]]);
        $ztd->begin_transaction();
        self::assertTrue($ztd->savepoint('scope'));
        $store->insert('items', [['id' => 2]]);
        self::assertTrue($ztd->query('ROLLBACK TO SAVEPOINT scope'));
        self::assertSame([['id' => 1]], $store->get('items'));
        $connection->close();
    }

    public function testRelease_savepointRemovesTheNativeSavepoint(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $store = new ShadowStore();
        $rewriter = self::createStub(SqlRewriter::class);
        $rewriter->method('transactionStatement')->willReturnCallback((new MySqlTransactionStatementParser())->parse(...));
        $factory = self::createStub(SessionFactory::class);
        $factory->method('create')->willReturnCallback(static fn (ConnectionInterface $native, ZtdConfig $config): Session => new Session($rewriter, $store, new ResultSelectRunner(), $config, $native));
        $ztd = ZtdMysqli::fromMysqli($connection, null, $factory);
        $ztd->begin_transaction();
        $ztd->savepoint('scope');
        self::assertTrue($ztd->release_savepoint('scope'));
        $this->expectException(mysqli_sql_exception::class);
        $ztd->query('ROLLBACK TO SAVEPOINT scope');
        $connection->close();
    }

    public function testReal_queryExecutesTheNativeQueryWhenDisabled(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $ztd->disableZtd();
        self::assertTrue($ztd->real_query('SELECT 7 AS id'));
        $result = $connection->store_result();
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['id' => '7']], $result->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testMulti_queryExposesEachNativeResult(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        self::assertTrue($ztd->multi_query('SELECT 1 AS id; SELECT 2 AS id'));
        $first = $ztd->store_result();
        self::assertInstanceOf(mysqli_result::class, $first);
        self::assertSame([['id' => '1']], $first->fetch_all(MYSQLI_ASSOC));
        self::assertTrue($ztd->more_results());
        self::assertTrue($ztd->next_result());
        $second = $ztd->store_result();
        self::assertInstanceOf(mysqli_result::class, $second);
        self::assertSame([['id' => '2']], $second->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testMore_resultsObservesPendingNativeStatements(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $connection->multi_query('SELECT 1; SELECT 2');
        $first = $connection->store_result();
        self::assertInstanceOf(mysqli_result::class, $first);
        $first->free();
        self::assertTrue($ztd->more_results());
        $connection->next_result();
        $second = $connection->store_result();
        self::assertInstanceOf(mysqli_result::class, $second);
        $second->free();
        self::assertFalse($ztd->more_results());
        $connection->close();
    }

    public function testNext_resultAdvancesTheNativeResultSequence(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $connection->multi_query('SELECT 1; SELECT 2');
        $first = $connection->store_result();
        self::assertInstanceOf(mysqli_result::class, $first);
        $first->free();
        self::assertTrue($ztd->next_result());
        $second = $connection->store_result();
        self::assertInstanceOf(mysqli_result::class, $second);
        $second->free();
        self::assertFalse($ztd->next_result());
        $connection->close();
    }

    public function testSelect_dbChangesTheNativeDatabase(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        self::assertTrue($ztd->select_db('information_schema'));
        $result = $connection->query('SELECT DATABASE() AS name');
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['name' => 'information_schema']], $result->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testSet_charsetChangesTheNativeEncoding(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        self::assertTrue($ztd->set_charset('latin1'));
        self::assertSame('latin1', $connection->character_set_name());
        $connection->close();
    }

    public function testCharacter_set_nameReadsTheNativeEncoding(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $connection->set_charset('latin1');
        self::assertSame('latin1', $ztd->character_set_name());
        $connection->close();
    }

    public function testReal_escape_stringEscapesWithTheNativeConnection(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        self::assertSame("O\\'Reilly", $ztd->real_escape_string("O'Reilly"));
        $connection->close();
    }

    public function testEscape_stringRetainsTheNativeAliasBehavior(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        self::assertSame("O\\'Reilly", $ztd->escape_string("O'Reilly"));
        $connection->close();
    }

    public function testChange_userChangesTheSelectedDatabase(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        self::assertTrue($ztd->change_user('root', 'root', 'information_schema'));
        $result = $connection->query('SELECT DATABASE() AS name');
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['name' => 'information_schema']], $result->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testGet_charsetReturnsTheNativeCharacterSet(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $connection->set_charset('latin1');
        $charset = $ztd->get_charset();
        self::assertNotNull($charset);
        self::assertSame('latin1', get_object_vars($charset)['charset']);
        $connection->close();
    }

    public function testGet_server_infoReturnsTheNativeServerVersion(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        self::assertSame($connection->server_info, $ztd->get_server_info());
        $connection->close();
    }

    public function testGet_connection_statsReturnsNativeMeasurements(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $stats = $ztd->get_connection_stats();
        self::assertArrayHasKey('bytes_sent', $stats);
        self::assertGreaterThan(0, $stats['bytes_sent']);
        $connection->close();
    }

    public function testGet_warningsReturnsTheNativeWarning(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $connection->query("SELECT CAST('invalid' AS UNSIGNED)");
        $warning = $ztd->get_warnings();
        self::assertInstanceOf(mysqli_warning::class, $warning);
        self::assertSame(1292, $warning->errno);
        $connection->close();
    }

    public function testDump_debug_infoRequestsServerDiagnostics(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        self::assertTrue($ztd->dump_debug_info());
        $connection->close();
    }

    public function testDebugAcceptsNativeTraceOptions(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        self::assertTrue($ztd->debug(''));
        $connection->close();
    }

    public function testOptionsConfiguresTheNativeConnection(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        self::assertTrue($ztd->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5));
        $connection->close();
    }

    public function testSet_optPreservesTheNativeOptionsAlias(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        self::assertTrue($ztd->set_opt(MYSQLI_OPT_CONNECT_TIMEOUT, 5));
        $connection->close();
    }

    public function testStatReturnsNativeServerStatus(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $status = $ztd->stat();
        self::assertIsString($status);
        self::assertStringContainsString('Uptime:', $status);
        $connection->close();
    }

    public function testStmt_initCreatesAUsableNativeStatement(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $statement = $ztd->stmt_init();
        self::assertTrue($statement->prepare('SELECT 7 AS id'));
        self::assertTrue($statement->execute());
        $result = $statement->get_result();
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['id' => 7]], $result->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testStore_resultReadsTheNativeBufferedResult(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $connection->real_query('SELECT 7 AS id');
        $result = $ztd->store_result();
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['id' => '7']], $result->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testUse_resultReadsTheNativeUnbufferedResult(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $connection->real_query('SELECT 7 AS id');
        $result = $ztd->use_result();
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['id' => '7']], $result->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testThread_safeMatchesTheNativeDriver(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        self::assertSame($connection->thread_safe(), $ztd->thread_safe());
        $connection->close();
    }

    public function testPollUsesAndUpdatesNativeConnectionArrays(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $connection->query('SELECT 7 AS id', MYSQLI_ASYNC);
        $read = [$connection];
        $error = [];
        $reject = [];
        self::assertSame(1, ZtdMysqli::poll($read, $error, $reject, 5));
        self::assertSame([$connection], $read);
        self::assertSame([], $error);
        self::assertSame([], $reject);
        $result = $connection->reap_async_query();
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['id' => '7']], $result->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testReap_async_queryReadsTheCompletedNativeResult(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $connection->query('SELECT 7 AS id', MYSQLI_ASYNC);
        $read = [$connection];
        $error = [];
        $reject = [];
        self::assertSame(1, mysqli::poll($read, $error, $reject, 5));
        $result = $ztd->reap_async_query();
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['id' => '7']], $result->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testCloseReleasesTheNativeConnection(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $thread = $connection->thread_id;
        self::assertTrue($ztd->close());
        $observer = new mysqli($host, 'root', 'root', 'test', $port);
        $result = $observer->query('SELECT ID FROM information_schema.PROCESSLIST WHERE ID = ' . $thread);
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([], $result->fetch_all(MYSQLI_ASSOC));
        $observer->close();
    }

    public function testReal_connectConnectsAnInitializedNativeHandle(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $connection->close();
        $connection = new mysqli();
        $rewriter = self::createStub(SqlRewriter::class);
        $factory = self::createStub(SessionFactory::class);
        $factory->method('create')->willReturnCallback(static fn (ConnectionInterface $native, ZtdConfig $config): Session => new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $native));
        $ztd = ZtdMysqli::fromMysqli($connection, null, $factory);
        self::assertTrue($ztd->real_connect($host, 'root', 'root', 'test', $port));
        $ztd->disableZtd();
        $result = $ztd->query('SELECT DATABASE() AS name');
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['name' => 'test']], $result->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testPingChecksTheNativeConnection(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        set_error_handler(static fn (int $severity, string $message): bool => $severity === E_DEPRECATED && str_contains($message, 'mysqli::ping'));
        try {
            self::assertTrue($ztd->ping());
        } finally {
            restore_error_handler();
            $connection->close();
        }
    }

    public function testGet_client_infoReportsTheClientLibrary(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        set_error_handler(static fn (int $severity, string $message): bool => $severity === E_DEPRECATED && str_contains($message, 'mysqli::get_client_info'));
        try {
            self::assertSame(mysqli_get_client_info(), $ztd->get_client_info());
        } finally {
            restore_error_handler();
            $connection->close();
        }
    }

    public function testInitRetainsTheNativeInitializationContract(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        set_error_handler(static fn (int $severity, string $message): bool => $severity === E_DEPRECATED && str_contains($message, 'mysqli::init'));
        try {
            self::assertTrue($ztd->init());
        } finally {
            restore_error_handler();
            $connection->close();
        }
    }

    public function testRefreshSendsTheNativeRefreshCommand(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        set_error_handler(static fn (int $severity, string $message): bool => $severity === E_DEPRECATED && str_contains($message, 'mysqli::refresh'));
        try {
            self::assertTrue($ztd->refresh(MYSQLI_REFRESH_STATUS));
        } finally {
            restore_error_handler();
            $connection->close();
        }
    }

    public function testSsl_setAcceptsNativeTlsConfiguration(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        set_error_handler(static fn (int $severity, string $message): bool => $severity === E_DEPRECATED && str_contains($message, 'mysqli::ssl_set'));
        try {
            self::assertTrue($ztd->ssl_set(null, null, null, null, null));
        } finally {
            restore_error_handler();
            $connection->close();
        }
    }

    public function testConnectUsesTheSuppliedNativeCredentials(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli();
        $factory = self::createStub(SessionFactory::class);
        $rewriter = self::createStub(SqlRewriter::class);
        $factory->method('create')->willReturnCallback(static fn (ConnectionInterface $native, ZtdConfig $config): Session => new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $native));
        $ztd = ZtdMysqli::fromMysqli($connection, null, $factory);
        self::assertTrue($ztd->connect($host, 'root', 'root', 'test', $port));
        $result = $connection->query('SELECT DATABASE() AS name');
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['name' => 'test']], $result->fetch_all(MYSQLI_ASSOC));
        $connection->close();
    }

    public function testKillTerminatesTheSpecifiedNativeConnection(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $port = $container->getMappedPort(3306);
        self::assertIsInt($port);
        $host = str_replace('localhost', '127.0.0.1', $container->getHost());
        $connection = new mysqli($host, 'root', 'root', 'test', $port);
        $ztd = ZtdMysqli::fromMysqli($connection);
        $other = new mysqli($host, 'root', 'root', 'test', $port);
        set_error_handler(static fn (int $severity, string $message): bool => $severity === E_DEPRECATED && str_contains($message, 'mysqli::kill'));
        try {
            self::assertTrue($ztd->kill($other->thread_id));
        } finally {
            restore_error_handler();
        }
        $this->expectException(mysqli_sql_exception::class);
        $other->query('SELECT 1');
    }
}
