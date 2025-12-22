<?php

declare(strict_types=1);

namespace Tests\Unit;

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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
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

    public function testEnableAndDisableZtd(): void
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

    public function testRealQueryWhenZtdDisabledDelegatesToInner(): void
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

    public function testMultiQueryDelegatesToInner(): void
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
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use ($rewriter): Session {
                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->beginTransactionReturn = true;

        self::assertTrue($ztd->begin_transaction());
        self::assertSame(0, $innerMysqli->beginTransactionCalledWithFlags);
    }

    public function testCommitDelegatesToInner(): void
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

        $innerMysqli->commitReturn = true;

        self::assertTrue($ztd->commit());
        self::assertSame(0, $innerMysqli->commitCalledWithFlags);
    }

    public function testRollbackDelegatesToInner(): void
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

        $innerMysqli->rollbackReturn = true;

        self::assertTrue($ztd->rollback());
        self::assertSame(0, $innerMysqli->rollbackCalledWithFlags);
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

    public function testSelectDbDelegatesToInner(): void
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

    public function testRealEscapeStringDelegatesToInner(): void
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

    public function testExecuteQueryWhenZtdDisabledDelegatesToInner(): void
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
