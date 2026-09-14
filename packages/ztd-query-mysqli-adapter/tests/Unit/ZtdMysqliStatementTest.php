<?php

declare(strict_types=1);

namespace Tests\Unit;

use Containers\MySql80Container;
use Containers\MySql84Container;
use mysqli;
use mysqli_result;
use mysqli_stmt;
use mysqli_warning;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultColumnExtractor;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultStatement;
use ZtdQuery\Adapter\Mysqli\MysqliResultProcessor;
use ZtdQuery\Adapter\Mysqli\MysqliStatementBindingBridge;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliStatement;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\MySql\MySqlResultColumnTypeResolver;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Session;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ZtdMysqliStatement::class)]
#[Large]
#[UsesClass(MysqliStatementBindingBridge::class)]
#[UsesClass(MysqliResultProcessor::class)]
#[UsesClass(MysqliResultStatement::class)]
#[UsesClass(MysqliResultColumnExtractor::class)]
#[UsesClass(ZtdMysqliException::class)]
final class ZtdMysqliStatementTest extends TestCase
{
    #[DataProvider('providerReadPlans')]
    public function testExecuteHonorsReadAndUnplannedParameters(?QueryKind $kind, bool $withParams): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $sql = $withParams ? 'SELECT ? AS value' : 'SELECT 7 AS value';
            $native = $connection->prepare($sql);
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $plan = $kind === null ? null : new RewritePlan($sql, $kind);
            $statement = new ZtdMysqliStatement($native, $session, $plan);
            self::assertTrue($statement->execute($withParams ? [42] : null));
            $result = $statement->get_result();
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame([['value' => $withParams ? '42' : 7]], $result->fetch_all(MYSQLI_ASSOC));
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    /**
     * @return array<string, array{?QueryKind, bool}>
     */
    public static function providerReadPlans(): array
    {
        return ['unplanned' => [null, false], 'unplanned-params' => [null, true], 'read' => [QueryKind::READ, false], 'read-params' => [QueryKind::READ, true]];
    }

    public function testExecuteDoesNotRunASkippedPlan(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $connection->query('CREATE TEMPORARY TABLE skipped_rows (id INT)');
            $native = $connection->prepare('INSERT INTO skipped_rows VALUES (1)');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, new RewritePlan('skipped', QueryKind::SKIPPED));
            self::assertFalse($statement->execute());
            $rows = $connection->query('SELECT * FROM skipped_rows');
            self::assertInstanceOf(mysqli_result::class, $rows);
            self::assertSame([], $rows->fetch_all(MYSQLI_ASSOC));
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testExecuteAppliesRowsToTheShadowStore(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT ? AS id');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $plan = new RewritePlan('SELECT ? AS id', QueryKind::WRITE_SIMULATED, new InsertMutation('items'));
            $statement = new ZtdMysqliStatement($native, $session, $plan);
            self::assertTrue($statement->execute([42]));
            self::assertSame([['id' => '42']], $store->get('items'));
            self::assertSame(1, $statement->ztdAffectedRows());
            self::assertSame(1, $statement->num_rows());
            self::assertNull($statement->fetch());
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testGet_resultConsumesTheCachedNativeResultOnce(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT 7 AS id');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, new RewritePlan('SELECT 7 AS id', QueryKind::WRITE_SIMULATED, new InsertMutation('items')));
            self::assertTrue($statement->execute());
            self::assertInstanceOf(mysqli_result::class, $statement->get_result());
            self::assertFalse($statement->get_result());
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testGet_resultReturnsFalseForAStatementWithoutRows(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('DO 1');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, new RewritePlan('DO 1', QueryKind::WRITE_SIMULATED, new InsertMutation('items')));
            self::assertTrue($statement->execute());
            self::assertSame(0, $statement->ztdAffectedRows());
            self::assertSame(0, $statement->num_rows());
            self::assertFalse($statement->get_result());
            self::assertFalse($statement->get_result());
            self::assertNull($statement->fetch());
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    #[DataProvider('providerFailedPlans')]
    public function testExecuteReturnsFalseForNativeFailures(?QueryKind $kind, bool $withParams): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $connection->query('CREATE TEMPORARY TABLE duplicate_keys (id INT PRIMARY KEY)');
            $connection->query('INSERT INTO duplicate_keys VALUES (1)');
            $sql = $withParams ? 'INSERT INTO duplicate_keys VALUES (?)' : 'INSERT INTO duplicate_keys VALUES (1)';
            $native = $connection->prepare($sql);
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $plan = $kind === null ? null : new RewritePlan($sql, $kind, new InsertMutation('items'));
            $statement = new ZtdMysqliStatement($native, $session, $plan);
            mysqli_report(MYSQLI_REPORT_OFF);
            try {
                self::assertFalse($statement->execute($withParams ? [1] : null));
                self::assertSame([], $store->get('items'));
            } finally {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                $connection->close();
            }
        } finally {
            $container->stop();
        }
    }

    /**
     * @return array<string, array{?QueryKind, bool}>
     */
    public static function providerFailedPlans(): array
    {
        return ['unplanned' => [null, false], 'read' => [QueryKind::READ, true], 'write' => [QueryKind::WRITE_SIMULATED, false], 'write-params' => [QueryKind::WRITE_SIMULATED, true]];
    }

    public function testFetchWritesBoundResultVariables(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT 7 AS id');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            $id = null;
            self::assertTrue($statement->bind_result($id));
            self::assertTrue($statement->execute());
            self::assertTrue($statement->fetch());
            self::assertSame(7, $id);
            self::assertNull($statement->fetch());
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testStore_resultBuffersTheNativeRows(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT 1 UNION ALL SELECT 2');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            self::assertTrue($statement->execute());
            self::assertTrue($statement->store_result());
            self::assertSame(2, $statement->num_rows());
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testData_seekMovesTheNativeCursor(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT 1 AS id UNION ALL SELECT 2');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            self::assertTrue($statement->execute());
            self::assertTrue($statement->store_result());
            $id = null;
            $statement->bind_result($id);
            $statement->data_seek(1);
            self::assertTrue($statement->fetch());
            self::assertSame(2, $id);
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testFree_resultReleasesBufferedRows(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT 1 UNION ALL SELECT 2');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            $statement->execute();
            $statement->store_result();
            self::assertSame(2, $statement->num_rows());
            $statement->free_result();
            self::assertSame(0, $native->num_rows());
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testNum_rowsReturnsTheNativeBufferedCount(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT 1 UNION ALL SELECT 2');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            $native->execute();
            $native->store_result();
            self::assertSame(2, $statement->num_rows());
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testZtdAffectedRowsReturnsTheNativeWriteCount(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('DO 1');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            $statement->execute();
            self::assertSame(0, $statement->ztdAffectedRows());
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testResult_metadataReturnsNativeColumnInformation(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare("SELECT 7 AS id, 'Alice' AS name");
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            $result = $statement->result_metadata();
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame(['id', 'name'], array_column($result->fetch_fields(), 'name'));
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testAttr_setUpdatesTheNativeCursorMode(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT 1');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            self::assertTrue($statement->attr_set(MYSQLI_STMT_ATTR_CURSOR_TYPE, MYSQLI_CURSOR_TYPE_READ_ONLY));
            self::assertSame(MYSQLI_CURSOR_TYPE_READ_ONLY, $native->attr_get(MYSQLI_STMT_ATTR_CURSOR_TYPE));
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testAttr_getReadsTheConfiguredNativeCursorMode(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT 1');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            $native->attr_set(MYSQLI_STMT_ATTR_CURSOR_TYPE, MYSQLI_CURSOR_TYPE_READ_ONLY);
            self::assertSame(MYSQLI_CURSOR_TYPE_READ_ONLY, $statement->attr_get(MYSQLI_STMT_ATTR_CURSOR_TYPE));
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testGet_warningsReturnsTheNativeWarning(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $connection->query('CREATE TEMPORARY TABLE warning_values (value TINYINT)');
            $native = $connection->prepare('INSERT IGNORE INTO warning_values VALUES (1000)');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            $statement->execute();
            $warning = $statement->get_warnings();
            self::assertInstanceOf(mysqli_warning::class, $warning);
            self::assertSame(1264, $warning->errno);
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testMore_resultsReturnsFalseAfterASingleResult(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT 1');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            $statement->execute();
            self::assertFalse($statement->more_results());
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testNext_resultReturnsFalseWithoutAnotherResult(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT 1');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            $statement->execute();
            $statement->store_result();
            self::assertFalse($statement->next_result());
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testPrepareReplacesTheDelegatedStatement(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT 1');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            self::assertTrue($statement->prepare('SELECT 42 AS value'));
            self::assertTrue($statement->execute());
            $result = $statement->get_result();
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame([['value' => 42]], $result->fetch_all(MYSQLI_ASSOC));
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testSend_long_dataConcatenatesBinaryChunks(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT ? AS value');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            $value = null;
            $statement->bind_param('b', $value);
            self::assertTrue($statement->send_long_data(0, 'first'));
            self::assertTrue($statement->send_long_data(0, 'second'));
            self::assertTrue($statement->execute());
            $result = $statement->get_result();
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame([['value' => 'firstsecond']], $result->fetch_all(MYSQLI_ASSOC));
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testResetClearsTheSimulatedAffectedRowCount(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $store->set('items', [['id' => 7]]);
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT 7 AS id');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $plan = new RewritePlan('SELECT 7 AS id', QueryKind::WRITE_SIMULATED, new InsertMutation('items', ['id'], true));
            $statement = new ZtdMysqliStatement($native, $session, $plan);
            self::assertTrue($statement->execute());
            self::assertSame(0, $statement->ztdAffectedRows());
            self::assertTrue($statement->reset());
            self::assertSame((int) $native->affected_rows, $statement->ztdAffectedRows());
            self::assertNotSame(0, $statement->ztdAffectedRows());
            $connection->close();
        } finally {
            $container->stop();
        }
    }

    public function testCloseClosesTheDelegatedStatement(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = $container->getData(mysqli::class);
            $store = new ShadowStore();
            $session = new Session(self::createStub(SqlRewriter::class), $store, new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class), resultColumnTypeResolver: new MySqlResultColumnTypeResolver());
            $native = $connection->prepare('SELECT 1');
            self::assertInstanceOf(mysqli_stmt::class, $native);
            $statement = new ZtdMysqliStatement($native, $session, null);
            self::assertTrue($statement->close());
            $closed = $connection->query("SHOW SESSION STATUS LIKE 'Com_stmt_close'");
            self::assertInstanceOf(mysqli_result::class, $closed);
            self::assertSame('1', $closed->fetch_all(MYSQLI_ASSOC)[0]['Value']);
            $connection->close();
        } finally {
            $container->stop();
        }
    }

}
