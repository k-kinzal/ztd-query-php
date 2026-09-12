<?php

declare(strict_types=1);

namespace Tests\Unit;

use mysqli;
use mysqli_result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\RecordingSessionFactory;
use Tests\Fixtures\StubMysqli;
use Tests\Fixtures\StubMysqliStmt;
use ZtdQuery\Adapter\Mysqli\MysqliConnection;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliStatement;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ZtdMysqli::class)]
#[\PHPUnit\Framework\Attributes\Large]
#[UsesClass(\ZtdQuery\Adapter\Mysqli\Native\MysqliPropertyReader::class)]
#[UsesClass(MysqliConnection::class)]
#[UsesClass(ZtdMysqliStatement::class)]
#[UsesClass(ZtdMysqliException::class)]
#[UsesClass(\ZtdQuery\Adapter\Mysqli\MysqliStatementBindingBridge::class)]
final class ZtdMysqliTest extends TestCase
{
    public function testFromMysqliCreatesInstanceWithFactory(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = new RecordingSessionFactory($rewriter, new ShadowStore());
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        self::assertTrue($ztd->isZtdEnabled());
        self::assertSame(1, $factory->calls);
        self::assertInstanceOf(MysqliConnection::class, $factory->connection);
    }

    public function testFromMysqliUsesExplicitConfig(): void
    {
        $innerMysqli = new StubMysqli();
        $config = ZtdConfig::default();
        $rewriter = static::createStub(SqlRewriter::class);

        $factory = new RecordingSessionFactory($rewriter, new ShadowStore());

        ZtdMysqli::fromMysqli($innerMysqli, $config, $factory);

        self::assertSame($config, $factory->config);
        self::assertSame(1, $factory->calls);
    }

    public function testEnableZtdRestoresTheSession(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = new RecordingSessionFactory($rewriter, new ShadowStore());
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
        $factory = new RecordingSessionFactory($rewriter, new ShadowStore());
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);
        $ztd->disableZtd();

        $nativeStmt = StubMysqliStmt::create();
        $innerMysqli->prepareReturn = $nativeStmt;

        $result = $ztd->prepare('SELECT 1');

        self::assertSame($nativeStmt, $result);
    }

    public function testPrepareWhenZtdEnabledReturnsZtdStatement(): void
    {
        $rewriter = static::createStub(SqlRewriter::class);
        $innerMysqli = new StubMysqli();
        $factory = new RecordingSessionFactory($rewriter, new ShadowStore());
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $nativeStmt = StubMysqliStmt::create();
        $plan = new RewritePlan('SELECT 1 /* rewritten */', QueryKind::READ);

        $rewriter->method('rewrite')
            ->willReturn($plan);

        $innerMysqli->prepareReturn = $nativeStmt;

        $result = $ztd->prepare('SELECT 1');

        self::assertInstanceOf(ZtdMysqliStatement::class, $result);
    }

    public function testPrepareWhenRewriteThrowsWrapsAsZtdException(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = new RecordingSessionFactory($rewriter, new ShadowStore());
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        /**
         * Default config uses Exception behavior for unsupported SQL.
         */
        /**
         * Session::rewrite catches UnsupportedSqlException and throws DatabaseException.
         */
        /**
         * ZtdMysqli::prepare catches DatabaseException and wraps as ZtdMysqliException.
         */
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
        $factory = new RecordingSessionFactory($rewriter, new ShadowStore());
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
        $factory = new RecordingSessionFactory($rewriter, new ShadowStore());
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);
        $ztd->disableZtd();

        $innerMysqli->queryReturn = true;

        $result = $ztd->query('SELECT 1');

        self::assertTrue($result);
    }

    public function testReal_queryWhenZtdDisabledDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = new RecordingSessionFactory($rewriter, new ShadowStore());
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);
        $ztd->disableZtd();

        $innerMysqli->realQueryReturn = true;

        self::assertTrue($ztd->real_query('SELECT 1'));
    }

    public function testMulti_queryDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = new RecordingSessionFactory($rewriter, new ShadowStore());
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->multiQueryReturn = true;

        self::assertTrue($ztd->multi_query('SELECT 1; SELECT 2'));
    }

    public function testBegin_transactionDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = new RecordingSessionFactory($rewriter, $store);
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
        $factory = new RecordingSessionFactory($rewriter, $store);
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
        $factory = new RecordingSessionFactory($rewriter, $store);
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
        $factory = new RecordingSessionFactory($rewriter, new ShadowStore());
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $ztd->close();

        self::assertTrue($innerMysqli->closeCalled);
    }

    public function testSelect_dbDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = new RecordingSessionFactory($rewriter, new ShadowStore());
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->selectDbReturn = true;

        self::assertTrue($ztd->select_db('test_db'));
    }

    public function testReal_escape_stringDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = new RecordingSessionFactory($rewriter, new ShadowStore());
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->realEscapeStringReturn = "O\\'Reilly";

        self::assertSame("O\\'Reilly", $ztd->real_escape_string("O'Reilly"));
    }

    public function testExecute_queryWhenZtdDisabledDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = new RecordingSessionFactory($rewriter, new ShadowStore());
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);
        $ztd->disableZtd();

        $innerMysqli->executeQueryReturn = true;

        self::assertTrue($ztd->execute_query('SELECT ?', [1]));
    }

    public function testSet_charsetPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->set_charset('latin1'));
        self::assertSame([['set_charset', ['latin1']]], $native->calls);
    }

    public function testEscape_stringPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $native->realEscapeStringReturn = 'escaped';
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame('escaped', $ztd->escape_string('quoted'));
        self::assertSame([['escape_string', ['quoted']]], $native->calls);
    }

    public function testPingPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->ping());
        self::assertSame([['ping', []]], $native->calls);
    }

    public function testCharacter_set_namePreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame('utf8mb4', $ztd->character_set_name());
        self::assertSame([['character_set_name', []]], $native->calls);
    }

    public function testChange_userPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->change_user('user', 'password', 'database'));
        self::assertSame([['change_user', ['user', 'password', 'database']]], $native->calls);
    }

    public function testConnectPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->connect('host', 'user', 'password', 'database', 13306, '/socket'));
        self::assertSame([['connect', ['host', 'user', 'password', 'database', 13306, '/socket']]], $native->calls);
    }

    public function testDebugPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->debug('d:t:o,/tmp/mysqli.trace'));
        self::assertSame([['debug', ['d:t:o,/tmp/mysqli.trace']]], $native->calls);
    }

    public function testDump_debug_infoPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->dump_debug_info());
        self::assertSame([['dump_debug_info', []]], $native->calls);
    }

    public function testGet_charsetPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(null, $ztd->get_charset());
        self::assertSame([['get_charset', []]], $native->calls);
    }

    public function testGet_client_infoPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame('test-client', $ztd->get_client_info());
        self::assertSame([['get_client_info', []]], $native->calls);
    }

    public function testGet_connection_statsPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(['bytes_sent' => '123'], $ztd->get_connection_stats());
        self::assertSame([['get_connection_stats', []]], $native->calls);
    }

    public function testGet_server_infoPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame('8.4.7', $ztd->get_server_info());
        self::assertSame([['get_server_info', []]], $native->calls);
    }

    public function testGet_warningsPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(false, $ztd->get_warnings());
        self::assertSame([['get_warnings', []]], $native->calls);
    }

    public function testInitPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->init());
        self::assertSame([['init', []]], $native->calls);
    }

    public function testKillPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->kill(77));
        self::assertSame([['kill', [77]]], $native->calls);
    }

    public function testMore_resultsPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(false, $ztd->more_results());
        self::assertSame([['more_results', []]], $native->calls);
    }

    public function testNext_resultPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(false, $ztd->next_result());
        self::assertSame([['next_result', []]], $native->calls);
    }

    public function testOptionsPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5));
        self::assertSame([['options', [MYSQLI_OPT_CONNECT_TIMEOUT, 5]]], $native->calls);
    }

    public function testReal_connectPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->real_connect('host', 'user', 'password', 'database', 13306, '/socket', MYSQLI_CLIENT_COMPRESS));
        self::assertSame([['real_connect', ['host', 'user', 'password', 'database', 13306, '/socket', MYSQLI_CLIENT_COMPRESS]]], $native->calls);
    }

    public function testReap_async_queryPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(false, $ztd->reap_async_query());
        self::assertSame([['reap_async_query', []]], $native->calls);
    }

    public function testRefreshPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->refresh(4));
        self::assertSame([['refresh', [4]]], $native->calls);
    }

    public function testRelease_savepointPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->release_savepoint('point'));
        self::assertSame([['release_savepoint', ['point']]], $native->calls);
    }

    public function testSavepointPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->savepoint('point'));
        self::assertSame([['savepoint', ['point']]], $native->calls);
    }

    public function testSsl_setPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->ssl_set('/key', '/certificate', '/ca', '/ca-path', 'cipher'));
        self::assertSame([['ssl_set', ['/key', '/certificate', '/ca', '/ca-path', 'cipher']]], $native->calls);
    }

    public function testStatPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame('Threads: 1', $ztd->stat());
        self::assertSame([['stat', []]], $native->calls);
    }

    public function testStmt_initPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertFalse($ztd->stmt_init()->get_result());
        self::assertSame([['stmt_init', []]], $native->calls);
    }

    public function testStore_resultPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(false, $ztd->store_result(1));
        self::assertSame([['store_result', [1]]], $native->calls);
    }

    public function testThread_safePreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(false, $ztd->thread_safe());
        self::assertSame([['thread_safe', []]], $native->calls);
    }

    public function testUse_resultPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(false, $ztd->use_result());
        self::assertSame([['use_result', []]], $native->calls);
    }

    public function testSet_optPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->set_opt(MYSQLI_OPT_CONNECT_TIMEOUT, 5));
        self::assertSame([['set_opt', [MYSQLI_OPT_CONNECT_TIMEOUT, 5]]], $native->calls);
    }

    public function testAutocommitPreservesNativeArgumentsAndResult(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();

        self::assertSame(true, $ztd->autocommit(false));
        self::assertSame([['autocommit', [false]]], $native->calls);
    }

    public function testDisableZtdTurnsOffTheSession(): void
    {
        $ztd = ZtdMysqli::fromMysqli(new StubMysqli(), null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        $ztd->disableZtd();
        self::assertFalse($ztd->isZtdEnabled());
    }

    public function testIsZtdEnabledDefaultsToTrue(): void
    {
        $ztd = ZtdMysqli::fromMysqli(new StubMysqli(), null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        self::assertTrue($ztd->isZtdEnabled());
    }

    public function testLastAffectedRowsFallsBackToTheNativeHandle(): void
    {
        $native = new StubMysqli();
        $ztd = ZtdMysqli::fromMysqli($native, null, new RecordingSessionFactory(self::createStub(SqlRewriter::class), new ShadowStore()));
        self::assertSame($native->affected_rows, $ztd->lastAffectedRows());
    }

    public function testPollPreservesTheNativeAsyncReferenceLists(): void
    {
        $native = new mysqli(...\Tests\Fixtures\MySqlContainer::connectionParameters());
        self::assertTrue($native->query('SELECT 7 AS id', MYSQLI_ASYNC));
        $read = [$native];
        $error = [];
        $reject = [];
        self::assertSame(1, ZtdMysqli::poll($read, $error, $reject, 5));
        self::assertSame([$native], $read);
        self::assertSame([], $error);
        self::assertSame([], $reject);
        $result = $native->reap_async_query();
        self::assertInstanceOf(mysqli_result::class, $result);
        self::assertSame([['id' => '7']], $result->fetch_all(MYSQLI_ASSOC));
        $native->close();
    }
}
