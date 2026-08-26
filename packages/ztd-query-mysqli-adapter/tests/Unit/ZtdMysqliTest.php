<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\StubMysqli;
use Tests\Fixtures\StubMysqliStmt;
use ZtdQuery\Adapter\Mysqli\MysqliConnection;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliStatement;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ZtdMysqli::class)]
#[UsesClass(MysqliConnection::class)]
#[UsesClass(ZtdMysqliStatement::class)]
#[UsesClass(ZtdMysqliException::class)]
final class ZtdMysqliTest extends TestCase
{
    public function testFromMysqliCreatesInstanceWithFactory(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        self::assertInstanceOf(ZtdMysqli::class, $ztd);
    }

    public function testFromMysqliUsesExplicitConfig(): void
    {
        $innerMysqli = new StubMysqli();
        $config = ZtdConfig::default();
        $rewriter = static::createStub(SqlRewriter::class);

        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->with(self::isInstanceOf(MysqliConnection::class), self::identicalTo($config))
            ->willReturnCallback(function (ConnectionInterface $conn, ZtdConfig $cfg) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $cfg, $conn);
            });

        ZtdMysqli::fromMysqli($innerMysqli, $config, $factory);
    }

    public function testEnableZtdEnableAndDisableZtd(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        self::assertTrue($ztd->isZtdEnabled());

        $ztd->disableZtd();
        self::assertFalse($ztd->isZtdEnabled());

        $ztd->enableZtd();
        self::assertTrue($ztd->isZtdEnabled());
    }

    public function testPrepareWhenZtdDisabledDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);
        $ztd->disableZtd();

        $nativeStmt = StubMysqliStmt::create();
        $innerMysqli->prepareReturn = $nativeStmt;

        $result = $ztd->prepare('SELECT 1');

        self::assertSame($nativeStmt, $result);
    }

    public function testPrepareWhenZtdEnabledReturnsZtdStatement(): void
    {
        $rewriter = static::createMock(SqlRewriter::class);
        $innerMysqli = new StubMysqli();
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $nativeStmt = StubMysqliStmt::create();
        $plan = new RewritePlan('SELECT 1 /* rewritten */', QueryKind::READ);

        $rewriter->expects(self::once())
            ->method('rewrite')
            ->with('SELECT 1')
            ->willReturn($plan);

        $innerMysqli->prepareReturn = $nativeStmt;

        $result = $ztd->prepare('SELECT 1');

        self::assertInstanceOf(ZtdMysqliStatement::class, $result);
    }

    public function testPrepareWhenRewriteThrowsWrapsAsZtdException(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        // Default config uses Exception behavior for unsupported SQL.
        // Session::rewrite catches UnsupportedSqlException and throws DatabaseException.
        // ZtdMysqli::prepare catches DatabaseException and wraps as ZtdMysqliException.
        $rewriter->method('rewrite')
            ->willThrowException(new UnsupportedSqlException('DROP DATABASE foo', 'Unsupported'));

        try {
            $ztd->prepare('DROP DATABASE foo');
            self::fail('Expected ZtdMysqliException');
        } catch (ZtdMysqliException $e) {
            self::assertStringContainsString('ZTD Write Protection', $e->getMessage());
            self::assertSame(0, $e->getCode());
            self::assertNotNull($e->getPrevious());
        }
    }

    public function testPrepareWhenInnerPrepareFails(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $plan = new RewritePlan('SELECT 1', QueryKind::READ);

        $rewriter->method('rewrite')->willReturn($plan);
        $innerMysqli->prepareReturn = false;

        $result = $ztd->prepare('SELECT 1');

        self::assertFalse($result);
    }

    public function testQueryWhenZtdDisabledDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);
        $ztd->disableZtd();

        $innerMysqli->queryReturn = true;

        $result = $ztd->query('SELECT 1');

        self::assertTrue($result);
    }

    public function testReal_queryRealQueryWhenZtdDisabledDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);
        $ztd->disableZtd();

        $innerMysqli->realQueryReturn = true;

        self::assertTrue($ztd->real_query('SELECT 1'));
    }

    public function testMulti_queryMultiQueryDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->multiQueryReturn = true;

        self::assertTrue($ztd->multi_query('SELECT 1; SELECT 2'));
    }

    public function testBeginTransactionDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter, $store): Session {
                return new Session($rewriter, $store, new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->beginTransactionReturn = true;

        self::assertTrue($ztd->begin_transaction());
        self::assertSame(0, $innerMysqli->beginTransactionCalledWithFlags);
        $store->insert('items', [['id' => 2]]);
        self::assertTrue($ztd->rollback());
        self::assertSame([['id' => 1]], $store->get('items'));
    }

    public function testCommitDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter, $store): Session {
                return new Session($rewriter, $store, new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->commitReturn = true;

        self::assertTrue($ztd->begin_transaction());
        $store->insert('items', [['id' => 2]]);
        self::assertTrue($ztd->commit());
        self::assertSame(0, $innerMysqli->commitCalledWithFlags);
        self::assertTrue($ztd->rollback());
        self::assertSame([['id' => 1], ['id' => 2]], $store->get('items'));
    }

    public function testRollbackDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter, $store): Session {
                return new Session($rewriter, $store, new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->rollbackReturn = true;

        self::assertTrue($ztd->begin_transaction());
        $store->insert('items', [['id' => 2]]);
        self::assertTrue($ztd->rollback());
        self::assertSame(0, $innerMysqli->rollbackCalledWithFlags);
        self::assertSame([['id' => 1]], $store->get('items'));
    }

    public function testCloseDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $ztd->close();

        self::assertTrue($innerMysqli->closeCalled);
    }

    public function testSelect_dbSelectDbDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->selectDbReturn = true;

        self::assertTrue($ztd->select_db('test_db'));
    }

    public function testReal_escape_stringRealEscapeStringDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->realEscapeStringReturn = "O\\'Reilly";

        self::assertSame("O\\'Reilly", $ztd->real_escape_string("O'Reilly"));
    }

    public function testExecute_queryExecuteQueryWhenZtdDisabledDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);
        $ztd->disableZtd();

        $innerMysqli->executeQueryReturn = true;

        self::assertTrue($ztd->execute_query('SELECT ?', [1]));
    }
}
