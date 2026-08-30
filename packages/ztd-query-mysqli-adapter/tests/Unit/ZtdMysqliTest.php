<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\FakeConnectionProperties;
use Tests\Fixtures\RecordingSessionFactory;
use Tests\Fixtures\RecordingSqlRewriter;
use Tests\Fixtures\StubMysqli;
use Tests\Fixtures\StubMysqliResult;
use Tests\Fixtures\StubMysqliStmt;
use ZtdQuery\Adapter\Mysqli\Driver\ConnectionState;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliConnection;
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
#[UsesClass(ConnectionState::class)]
#[UsesClass(MysqliConnection::class)]
#[UsesClass(ZtdMysqliStatement::class)]
#[UsesClass(ZtdMysqliException::class)]
#[UsesClass(\ZtdQuery\Adapter\Mysqli\Driver\MysqliProperties::class)]
final class ZtdMysqliTest extends TestCase
{
    public function testFromMysqliCreatesInstanceWithFactory(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        self::assertTrue($ztd->isZtdEnabled());

        self::assertCount(1, $factory->calls());
    }

    public function testFromMysqliUsesExplicitConfig(): void
    {
        $innerMysqli = new StubMysqli();
        $config = ZtdConfig::default();
        $rewriter = static::createStub(SqlRewriter::class);

        $factory = RecordingSessionFactory::answeringWith($rewriter);

        ZtdMysqli::fromMysqli($innerMysqli, $config, $factory);

        self::assertCount(1, $factory->calls());
    }

    public function testEnableZtdEnableAndDisableZtd(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        self::assertTrue($ztd->isZtdEnabled());

        $ztd->disableZtd();
        self::assertFalse($ztd->isZtdEnabled());

        $ztd->enableZtd();
        self::assertTrue($ztd->isZtdEnabled());

        self::assertCount(1, $factory->calls());
    }

    public function testPrepareWhenZtdDisabledDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);
        $ztd->disableZtd();

        $nativeStmt = StubMysqliStmt::create();
        $innerMysqli->prepareReturn = $nativeStmt;

        $result = $ztd->prepare('SELECT 1');

        self::assertSame($nativeStmt, $result);

        self::assertCount(1, $factory->calls());
    }

    public function testPrepareWhenZtdEnabledReturnsZtdStatement(): void
    {
        $plan = new RewritePlan('SELECT 1 /* rewritten */', QueryKind::READ);
        $rewriter = new RecordingSqlRewriter(
            static fn (string $sql): array => [$sql],
            static fn (string $sql): RewritePlan => $plan,
        );
        $innerMysqli = new StubMysqli();
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->prepareReturn = StubMysqliStmt::create();

        $result = $ztd->prepare('SELECT 1');

        self::assertInstanceOf(ZtdMysqliStatement::class, $result);
        self::assertSame(['SELECT 1'], $rewriter->rewritten);
        self::assertCount(1, $factory->calls());
    }

    public function testPrepareWhenRewriteThrowsWrapsAsZtdException(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

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

        self::assertCount(1, $factory->calls());
    }

    public function testPrepareWhenInnerPrepareFails(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $plan = new RewritePlan('SELECT 1', QueryKind::READ);

        $rewriter->method('rewrite')->willReturn($plan);
        $innerMysqli->prepareReturn = false;

        $result = $ztd->prepare('SELECT 1');

        self::assertFalse($result);

        self::assertCount(1, $factory->calls());
    }

    public function testQueryWhenZtdDisabledDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);
        $ztd->disableZtd();

        $innerMysqli->queryReturn = true;

        $result = $ztd->query('SELECT 1');

        self::assertTrue($result);

        self::assertCount(1, $factory->calls());
    }

    public function testReal_queryRealQueryWhenZtdDisabledDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);
        $ztd->disableZtd();

        $innerMysqli->realQueryReturn = true;

        self::assertTrue($ztd->real_query('SELECT 1'));

        self::assertCount(1, $factory->calls());
    }

    public function testMulti_queryMultiQueryDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->multiQueryReturn = true;

        self::assertTrue($ztd->multi_query('SELECT 1; SELECT 2'));

        self::assertCount(1, $factory->calls());
    }

    public function testBegin_transactionOpensOneOnTheShadowAsWellAsTheConnection(): void
    {
        $innerMysqli = new StubMysqli();
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = new RecordingSessionFactory(
            static fn (ConnectionInterface $connection, ZtdConfig $config): Session => new Session(
                $rewriter,
                $store,
                new ResultSelectRunner(),
                $config,
                $connection,
            ),
        );
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->beginTransactionReturn = true;

        self::assertTrue($ztd->begin_transaction());
        self::assertSame(0, $innerMysqli->beginTransactionCalledWithFlags);
        $store->insert('items', [['id' => 2]]);
        self::assertTrue($ztd->rollback());
        self::assertSame([['id' => 1]], $store->get('items'));

        self::assertCount(1, $factory->calls());
    }

    public function testCommitDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = new RecordingSessionFactory(
            static fn (ConnectionInterface $connection, ZtdConfig $config): Session => new Session(
                $rewriter,
                $store,
                new ResultSelectRunner(),
                $config,
                $connection,
            ),
        );
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->commitReturn = true;

        self::assertTrue($ztd->begin_transaction());
        $store->insert('items', [['id' => 2]]);
        self::assertTrue($ztd->commit());
        self::assertSame(0, $innerMysqli->commitCalledWithFlags);
        self::assertTrue($ztd->rollback());
        self::assertSame([['id' => 1], ['id' => 2]], $store->get('items'));

        self::assertCount(1, $factory->calls());
    }

    public function testRollbackDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = new RecordingSessionFactory(
            static fn (ConnectionInterface $connection, ZtdConfig $config): Session => new Session(
                $rewriter,
                $store,
                new ResultSelectRunner(),
                $config,
                $connection,
            ),
        );
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->rollbackReturn = true;

        self::assertTrue($ztd->begin_transaction());
        $store->insert('items', [['id' => 2]]);
        self::assertTrue($ztd->rollback());
        self::assertSame(0, $innerMysqli->rollbackCalledWithFlags);
        self::assertSame([['id' => 1]], $store->get('items'));

        self::assertCount(1, $factory->calls());
    }

    public function testCloseDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $ztd->close();

        self::assertTrue($innerMysqli->closeCalled);

        self::assertCount(1, $factory->calls());
    }

    public function testSelect_dbSelectDbDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->selectDbReturn = true;

        self::assertTrue($ztd->select_db('test_db'));

        self::assertCount(1, $factory->calls());
    }

    public function testReal_escape_stringRealEscapeStringDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);

        $innerMysqli->realEscapeStringReturn = "O\\'Reilly";

        self::assertSame("O\\'Reilly", $ztd->real_escape_string("O'Reilly"));

        self::assertCount(1, $factory->calls());
    }

    public function testExecute_queryExecuteQueryWhenZtdDisabledDelegatesToInner(): void
    {
        $innerMysqli = new StubMysqli();
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $ztd = ZtdMysqli::fromMysqli($innerMysqli, null, $factory);
        $ztd->disableZtd();

        $stmt = StubMysqliStmt::create();
        $innerMysqli->prepareReturn = $stmt;

        self::assertSame([true, [1]], [$ztd->execute_query('SELECT ?', [1]), $stmt->executeCalledWithParams]);

        self::assertCount(1, $factory->calls());
    }
    public function testDisableZtdLetsStatementsReachTheServer(): void
    {
        $ztd = $this->providerZtd(new StubMysqli());

        $ztd->disableZtd();

        self::assertFalse($ztd->isZtdEnabled());
    }

    public function testIsZtdEnabledSaysWritesAreShadowedFromTheStart(): void
    {
        self::assertTrue($this->providerZtd(new StubMysqli())->isZtdEnabled());
    }

    public function testLastAffectedRowsAnswersWhatTheConnectionSaysWhereZtdWroteNothing(): void
    {
        $properties = new FakeConnectionProperties(['affected_rows' => 4]);

        self::assertSame(4, $this->providerZtd(new StubMysqli(), $properties)->lastAffectedRows());
    }

    public function testAutocommitOpensAShadowTransactionWhenItIsTurnedOff(): void
    {
        $inner = new StubMysqli();
        $ztd = $this->providerZtd($inner);

        self::assertSame([true, ['autocommit:0']], [$ztd->autocommit(false), $inner->calls]);
    }

    public function testAutocommitClosesTheShadowTransactionWhenItIsTurnedOn(): void
    {
        $inner = new StubMysqli();
        $ztd = $this->providerZtd($inner);
        $ztd->autocommit(false);

        self::assertSame([true, ['autocommit:0', 'autocommit:1']], [$ztd->autocommit(true), $inner->calls]);
    }

    public function testSet_charsetPassesTheCharsetOnToTheConnection(): void
    {
        $inner = new StubMysqli();

        self::assertSame([true, ['set_charset:utf8mb4']], [$this->providerZtd($inner)->set_charset('utf8mb4'), $inner->calls]);
    }

    public function testEscape_stringWritesTheValueTheWayTheConnectionWouldEscapeIt(): void
    {
        $inner = new StubMysqli();
        $inner->realEscapeStringReturn = 'a\\"b';

        self::assertSame('a\\"b', $this->providerZtd($inner)->escape_string('a"b'));
    }

    public function testPingAsksTheConnectionWhetherItIsStillThere(): void
    {
        $inner = new StubMysqli();

        self::assertSame([true, ['ping']], [$this->providerZtd($inner)->ping(), $inner->calls]);
    }

    public function testCharacter_set_nameAnswersTheCharsetTheConnectionIsUsing(): void
    {
        $inner = new StubMysqli();
        $inner->name = 'latin1';

        self::assertSame('latin1', $this->providerZtd($inner)->character_set_name());
    }

    public function testChange_userPassesTheNewUserOnToTheConnection(): void
    {
        $inner = new StubMysqli();

        self::assertSame(
            [true, ['change_user:ada']],
            [$this->providerZtd($inner)->change_user('ada', 'secret', 'ztd'), $inner->calls],
        );
    }

    public function testConnectOpensTheConnectionItWasBuiltAround(): void
    {
        $inner = new StubMysqli();

        self::assertSame([true, ['connect']], [$this->providerZtd($inner)->connect('localhost'), $inner->calls]);
    }

    public function testDebugPassesTheOptionsOnToTheConnection(): void
    {
        $inner = new StubMysqli();

        self::assertSame([true, ['debug:d:t']], [$this->providerZtd($inner)->debug('d:t'), $inner->calls]);
    }

    public function testDump_debug_infoAsksTheConnectionToWriteWhatItKnows(): void
    {
        $inner = new StubMysqli();

        self::assertSame([true, ['dump_debug_info']], [$this->providerZtd($inner)->dump_debug_info(), $inner->calls]);
    }

    public function testGet_charsetAnswersWhatTheConnectionSaysAboutItsCharset(): void
    {
        $inner = new StubMysqli();
        $inner->name = 'latin1';

        $charset = $this->providerZtd($inner)->get_charset();

        self::assertSame(['charset' => 'latin1'], $charset === null ? [] : get_object_vars($charset));
    }

    public function testGet_client_infoAnswersWhatTheConnectionSaysAboutItsClient(): void
    {
        $inner = new StubMysqli();
        $inner->name = 'mysqlnd 8.5';

        self::assertSame('mysqlnd 8.5', $this->providerZtd($inner)->get_client_info());
    }

    public function testGet_connection_statsAnswersWhatTheConnectionCounted(): void
    {
        $inner = new StubMysqli();
        $inner->connectionStats = ['bytes_sent' => 42];

        self::assertSame(['bytes_sent' => 42], $this->providerZtd($inner)->get_connection_stats());
    }

    public function testGet_server_infoAnswersWhatTheConnectionSaysAboutTheServer(): void
    {
        $inner = new StubMysqli();
        $inner->name = '8.0.36';

        self::assertSame('8.0.36', $this->providerZtd($inner)->get_server_info());
    }

    public function testGet_warningsAnswersFalseWhereTheConnectionRaisedNone(): void
    {
        self::assertFalse($this->providerZtd(new StubMysqli())->get_warnings());
    }

    public function testInitAsksTheConnectionToReadyItself(): void
    {
        $inner = new StubMysqli();

        self::assertSame([true, ['init']], [$this->providerZtd($inner)->init(), $inner->calls]);
    }

    public function testKillPassesTheProcessOnToTheConnection(): void
    {
        $inner = new StubMysqli();

        self::assertSame([true, ['kill:17']], [$this->providerZtd($inner)->kill(17), $inner->calls]);
    }

    public function testMore_resultsAsksTheConnectionWhetherAnotherResultFollows(): void
    {
        self::assertTrue($this->providerZtd(new StubMysqli())->more_results());
    }

    public function testNext_resultMovesTheConnectionOnToItsNextResult(): void
    {
        $inner = new StubMysqli();

        self::assertSame([true, ['next_result']], [$this->providerZtd($inner)->next_result(), $inner->calls]);
    }

    public function testOptionsPassesTheOptionOnToTheConnection(): void
    {
        $inner = new StubMysqli();

        self::assertSame(
            [true, ['options:' . MYSQLI_OPT_CONNECT_TIMEOUT]],
            [$this->providerZtd($inner)->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5), $inner->calls],
        );
    }

    public function testReal_connectOpensTheConnectionItWasBuiltAround(): void
    {
        $inner = new StubMysqli();

        self::assertSame([true, ['real_connect']], [$this->providerZtd($inner)->real_connect('localhost'), $inner->calls]);
    }

    public function testReap_async_queryAnswersWhatTheConnectionHasReadyForIt(): void
    {
        $result = StubMysqliResult::create([['id' => 1]]);
        $inner = new StubMysqli();
        $inner->storedResult = $result;

        self::assertSame($result, $this->providerZtd($inner)->reap_async_query());
    }

    public function testRefreshPassesTheFlagsOnToTheConnection(): void
    {
        $inner = new StubMysqli();

        self::assertSame([true, ['refresh:1']], [$this->providerZtd($inner)->refresh(1), $inner->calls]);
    }

    public function testSavepointNamesTheSameSavepointInTheShadow(): void
    {
        $inner = new StubMysqli();

        self::assertSame([true, ['savepoint:sp1']], [$this->providerZtd($inner)->savepoint('sp1'), $inner->calls]);
    }

    public function testRelease_savepointLetsGoOfTheSameSavepointInTheShadow(): void
    {
        $inner = new StubMysqli();
        $ztd = $this->providerZtd($inner);
        $ztd->savepoint('sp1');

        self::assertSame(
            [true, ['savepoint:sp1', 'release_savepoint:sp1']],
            [$ztd->release_savepoint('sp1'), $inner->calls],
        );
    }

    public function testSsl_setPassesTheCertificatesOnToTheConnection(): void
    {
        $inner = new StubMysqli();

        self::assertSame([true, ['ssl_set']], [$this->providerZtd($inner)->ssl_set(null, null, null, null, null), $inner->calls]);
    }

    public function testStatAnswersWhatTheServerSaysItIsDoing(): void
    {
        $inner = new StubMysqli();
        $inner->statusLine = 'Uptime: 9';

        self::assertSame('Uptime: 9', $this->providerZtd($inner)->stat());
    }

    public function testStmt_initAnswersAStatementFromTheConnection(): void
    {
        self::assertInstanceOf(StubMysqliStmt::class, $this->providerZtd(new StubMysqli())->stmt_init());
    }

    public function testStore_resultAnswersWhatTheConnectionHeldBack(): void
    {
        $result = StubMysqliResult::create([['id' => 1]]);
        $inner = new StubMysqli();
        $inner->storedResult = $result;

        self::assertSame($result, $this->providerZtd($inner)->store_result());
    }

    public function testThread_safeAsksTheConnectionWhetherTheClientIsThreadSafe(): void
    {
        self::assertTrue($this->providerZtd(new StubMysqli())->thread_safe());
    }

    public function testUse_resultAnswersTheResultTheConnectionIsStillReading(): void
    {
        $result = StubMysqliResult::create([['id' => 1]]);
        $inner = new StubMysqli();
        $inner->storedResult = $result;

        self::assertSame($result, $this->providerZtd($inner)->use_result());
    }

    public function testSet_optPassesTheOptionOnToTheConnection(): void
    {
        $inner = new StubMysqli();

        self::assertSame(
            [true, ['set_opt:' . MYSQLI_OPT_CONNECT_TIMEOUT]],
            [$this->providerZtd($inner)->set_opt(MYSQLI_OPT_CONNECT_TIMEOUT, 5), $inner->calls],
        );
    }

    public function testPollAnswersFalseWhereNoConnectionWasHandedToIt(): void
    {
        $read = [];
        $error = [];
        $reject = [];
        set_error_handler(static fn (): bool => true);

        $polled = ZtdMysqli::poll($read, $error, $reject, 0);

        restore_error_handler();
        self::assertFalse($polled);
    }

    /**
     * @param StubMysqli $inner The connection ZTD is put in front of
     * @param FakeConnectionProperties|null $properties What that connection answers about itself
     *
     * @return ZtdMysqli The connection, with ZTD in front of it
     */
    public function providerZtd(StubMysqli $inner, ?FakeConnectionProperties $properties = null): ZtdMysqli
    {
        $rewriter = static::createStub(SqlRewriter::class);
        $factory = static::createStub(SessionFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn (ConnectionInterface $connection, ZtdConfig $config): Session => new Session(
                $rewriter,
                new ShadowStore(),
                new ResultSelectRunner(),
                $config,
                $connection,
            ),
        );

        return ZtdMysqli::fromMysqli($inner, null, $factory, $properties ?? new FakeConnectionProperties());
    }
    public function testRunWithoutZtdRunsAStatementWithNothingToBindOnTheConnection(): void
    {
        $inner = new StubMysqli();
        $inner->queryReturn = true;

        self::assertSame([true, 'SELECT 1'], [$this->providerZtd($inner)->runWithoutZtd('SELECT 1', null), $inner->queryCalledWith]);
    }

    public function testRunWithoutZtdAnswersFalseWhereTheConnectionWillNotPrepareTheStatement(): void
    {
        $inner = new StubMysqli();
        $inner->prepareReturn = false;

        self::assertFalse($this->providerZtd($inner)->runWithoutZtd('SELECT ?', [1]));
    }

    public function testRunWithoutZtdAnswersFalseWhereTheStatementWillNotRun(): void
    {
        $inner = new StubMysqli();
        $stmt = StubMysqliStmt::create();
        $stmt->executeReturn = false;
        $inner->prepareReturn = $stmt;

        self::assertFalse($this->providerZtd($inner)->runWithoutZtd('SELECT ?', [1]));
    }

    public function testRunWithoutZtdAnswersTheResultTheStatementHeld(): void
    {
        $result = StubMysqliResult::create([['id' => 1]]);
        $inner = new StubMysqli();
        $stmt = StubMysqliStmt::create();
        $stmt->getResultReturn = $result;
        $inner->prepareReturn = $stmt;

        self::assertSame($result, $this->providerZtd($inner)->runWithoutZtd('SELECT ?', [1]));
    }
}
