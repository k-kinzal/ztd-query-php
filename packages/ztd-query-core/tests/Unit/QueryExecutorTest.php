<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeConnection;
use Tests\Fake\FakeSqlRewriter;
use Tests\Fake\FakeStatement;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\ResultSet;
use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Exception\MissingPrimaryKeyException;
use ZtdQuery\Platform\CopySupport;
use ZtdQuery\Platform\CopyTarget;
use ZtdQuery\Platform\MissingResultColumnTypeResolver;
use ZtdQuery\Platform\ParameterBindingCompiler;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\QueryExecutor;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\RewriteRefusal;
use ZtdQuery\Schema\Key\CandidateKeySet;
use ZtdQuery\Schema\Key\ForeignKeyDefinition;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\Mutation\Row\InsertMutation;
use ZtdQuery\Shadow\Mutation\Row\UpdateMutation;
use ZtdQuery\Shadow\ReferentialIntegrityEnforcer;
use ZtdQuery\Shadow\Row\RowPairing;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactions;
use ZtdQuery\Sql\TransactionStatement;

#[CoversClass(QueryExecutor::class)]
#[UsesClass(\ZtdQuery\Schema\ColumnDeclaration::class)]
#[UsesClass(\ZtdQuery\Schema\Key\CandidateKeyMatch::class)]
#[UsesClass(ZtdConfig::class)]
#[UsesClass(\ZtdQuery\Session::class)]
#[UsesClass(\ZtdQuery\Schema\ViewDefinitionSet::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(ShadowTransactions::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(CandidateKeySet::class)]
#[UsesClass(ResultSelectRunner::class)]
#[UsesClass(ResultSet::class)]
#[UsesClass(DatabaseException::class)]
#[UsesClass(RewritePlan::class)]
#[UsesClass(UpdateMutation::class)]
#[UsesClass(InsertMutation::class)]
#[UsesClass(MutationRowIdentity::class)]
#[UsesClass(ForeignKeyDefinition::class)]
#[UsesClass(ForeignKeyViolationException::class)]
#[UsesClass(ReferentialIntegrityEnforcer::class)]
#[UsesClass(MissingPrimaryKeyException::class)]
#[UsesClass(CopyTarget::class)]
#[UsesClass(MissingResultColumnTypeResolver::class)]
#[UsesClass(TransactionStatement::class)]
#[UsesClass(\ZtdQuery\Shadow\ShadowApplication::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\MutationImpact::class)]
#[UsesClass(\ZtdQuery\GenericExecuteResult::class)]
#[UsesClass(\ZtdQuery\Shadow\ShadowSavepoint::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\RowConstraints::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\ConflictSearch::class)]
#[UsesClass(\ZtdQuery\Shadow\ForeignKeyCascade::class)]
#[UsesClass(\ZtdQuery\Shadow\ForeignKeyEnds::class)]
#[UsesClass(\ZtdQuery\Shadow\ForeignKeyIntegrity::class)]
#[UsesClass(\ZtdQuery\Shadow\ParentKeyLookup::class)]
#[UsesClass(\ZtdQuery\Shadow\Row\RowMatch::class)]
#[UsesClass(\ZtdQuery\Shadow\Row\RowMultiset::class)]
#[UsesClass(\ZtdQuery\Shadow\Row\TableTransition::class)]
#[UsesClass(\ZtdQuery\Shadow\TableTransitions::class)]
#[UsesClass(RewriteRefusal::class)]
#[UsesClass(RowPairing::class)]
#[UsesClass(\ZtdQuery\Schema\RowSet::class)]
final class QueryExecutorTest extends TestCase
{
    public function testDisableEnableDisableEnableAndDisable(): void
    {
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $rewriter = new FakeSqlRewriter($shadowStore, $registry);
        $connection = new FakeConnection();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            $rewriter,
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            $connection,
        );

        self::assertTrue($executor->session()->isEnabled());
        self::assertNull($executor->session()->tableDefinition('users'));
        self::assertNull($executor->platform()->copySupport());
        self::assertNull($executor->copyTarget('users', null));
        self::assertNull($executor->platform()->parameterBindingCompiler());
        self::assertInstanceOf(MissingResultColumnTypeResolver::class, $executor->platform()->resultColumnTypeResolver());

        $executor->session()->disable();
        self::assertFalse($executor->session()->isEnabled());

        $executor->session()->enable();
        self::assertTrue($executor->session()->isEnabled());
    }

    public function testTableDefinitionReturnsRegisteredSchemaOrNull(): void
    {
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], ['id'], []);
        $registry->register('users', $definition);
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($shadowStore, $registry),
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        self::assertSame($definition, $executor->session()->tableDefinition('users'));
        self::assertNull($executor->session()->tableDefinition('missing'));
    }

    public function testParameterBindingCompilerResultColumnTypeResolverParameterBindingCompilerDelegatesCopyTargetsToTheConfiguredPlatformSupport(): void
    {
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], ['id'], []);
        $registry->register('users', $definition);
        $target = new CopyTarget(['public', 'users'], ['id']);
        $copy = self::createStub(CopySupport::class);
        $copy->method('tableName')->willReturnMap([
            ['public.users', 'users'],
            ['missing', 'missing'],
        ]);
        $copy->method('target')->willReturn($target);
        $compiler = self::createStub(ParameterBindingCompiler::class);
        $typeResolver = self::createStub(ResultColumnTypeResolver::class);
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($shadowStore, $registry),
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
            copySupport: $copy,
            parameterBindingCompiler: $compiler,
            resultColumnTypeResolver: $typeResolver,
        );

        self::assertSame($copy, $executor->platform()->copySupport());
        self::assertSame($target, $executor->copyTarget('public.users', 'id'));
        self::assertNull($executor->copyTarget('missing', null));
        self::assertSame($compiler, $executor->platform()->parameterBindingCompiler());
        self::assertSame($typeResolver, $executor->platform()->resultColumnTypeResolver());
    }

    public function testSplitStatementsUsesPlatformRewriter(): void
    {
        $shadowStore = new ShadowStore();
        $rewriter = new FakeSqlRewriter($shadowStore, new TableDefinitionRegistry());
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            $rewriter,
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
        );

        self::assertSame(
            ['SELECT 1', 'SELECT 2'],
            $executor->splitStatements(' SELECT 1; SELECT 2 '),
        );
    }

    public function testSessionTransactionsRestoreTheSameCatalogUsedByTheRewriter(): void
    {
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INT'], ['id'], [], []);
        $registry->register('users', $definition);
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($shadowStore, $registry),
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        $executor->session()->transactions()->begin();
        $registry->unregister('users');
        $executor->session()->transactions()->rollBack();

        self::assertSame($definition, $registry->get('users'));
    }

    public function testMutationFailureIsConvertedToDatabaseException(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($shadowStore, $registry),
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
        );
        $plan = new RewritePlan(
            "SELECT 1 AS id, 'Bob' AS name",
            QueryKind::WRITE_SIMULATED,
            new UpdateMutation('users', []),
        );

        try {
            $executor->processExecutedStatement($plan, new FakeStatement([['id' => 1, 'name' => 'Bob']]));
            self::fail('Expected a database exception.');
        } catch (DatabaseException $exception) {
            self::assertSame(0, $exception->getCode());
            self::assertInstanceOf(MissingPrimaryKeyException::class, $exception->getPrevious());
        }
    }

    public function testForeignKeyFailureRestoresShadowState(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('parents', []);
        $shadowStore->set('children', []);
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(['parent_id'], 'parents', ['id'])],
        ));
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($shadowStore, $registry),
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );
        $plan = new RewritePlan(
            'SELECT 1 AS id, 999 AS parent_id',
            QueryKind::WRITE_SIMULATED,
            new InsertMutation('children'),
        );

        try {
            $executor->processExecutedStatement($plan, new FakeStatement([['id' => 1, 'parent_id' => 999]]));
            self::fail('Expected a database exception.');
        } catch (DatabaseException $exception) {
            self::assertInstanceOf(ForeignKeyViolationException::class, $exception->getPrevious());
            self::assertSame([], $shadowStore->get('children'));
        }
    }

    public function testIsEnabledFollowsWhatWasAskedFor(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        self::assertTrue($executor->session()->isEnabled());

        $executor->session()->disable();

        self::assertFalse($executor->session()->isEnabled());
    }

    public function testDisableStopsZtdWithoutTouchingTheShadow(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        $executor->session()->disable();

        self::assertSame([['id' => 1]], $store->get('users'));
    }

    public function testShouldExecuteIsFalseOnlyForAPlanNothingIsToBeRunFor(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        self::assertTrue($executor->shouldExecute(new RewritePlan('SELECT 1', QueryKind::READ)));
        self::assertFalse($executor->shouldExecute(new RewritePlan('SELECT 1', QueryKind::SKIPPED)));
    }

    public function testNeedsPostProcessingIsTrueForTheKindsThatChangeTheShadow(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        self::assertTrue($executor->needsPostProcessing(new RewritePlan('x', QueryKind::WRITE_SIMULATED)));
        self::assertTrue($executor->needsPostProcessing(new RewritePlan('x', QueryKind::DDL_SIMULATED)));
        self::assertFalse($executor->needsPostProcessing(new RewritePlan('x', QueryKind::READ)));
    }

    public function testCreateEmptyWriteResultAnswersASimulatedWriteWithNothingToFetch(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        $result = $executor->createEmptyWriteResult();

        self::assertSame(QueryKind::WRITE_SIMULATED, $result->kind());
        self::assertSame([], $result->fetchAll());
    }

    public function testLastInsertIdIsFalseUntilSomethingHasBeenInserted(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        self::assertFalse($executor->session()->lastInsertId());
    }

    public function testTransactionStatementIsNothingForAStatementThatIsNotOne(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        self::assertNull($executor->transactionStatement('SELECT 1'));
    }

    public function testCommitTransactionKeepsWhatTheTransactionDid(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        $executor->session()->transactions()->begin();
        $store->set('users', []);
        $executor->session()->transactions()->commit();
        $executor->session()->transactions()->rollBack();

        self::assertSame([], $store->get('users'));
    }

    public function testApplyTransactionStatementDoesWhatTheStatementSays(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        TransactionStatement::begin()->apply($executor->session()->transactions());
        $store->set('users', []);
        TransactionStatement::rollback()->apply($executor->session()->transactions());

        self::assertSame([['id' => 1]], $store->get('users'));
    }

    public function testCopySupportIsNothingWhereTheDialectHasNoCopy(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        self::assertNull($executor->platform()->copySupport());
    }

    public function testCopyTargetIsNothingWhereTheDialectHasNoCopy(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        self::assertNull($executor->copyTarget('users', null));
    }

    public function testParameterBindingCompilerIsNothingWhereTheDriverBindsThemItself(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        self::assertNull($executor->platform()->parameterBindingCompiler());
    }

    public function testRewriteAnswersThePlanTheRewriterGives(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        $plan = $executor->rewrite('SELECT 1');

        self::assertSame(QueryKind::READ, $plan->kind());
    }

    public function testProcessExecutedStatementReadsAReadStatementStraightBack(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );
        $plan = new RewritePlan('SELECT 1', QueryKind::READ);

        $result = $executor->processExecutedStatement($plan, new FakeStatement([['id' => 1]]));

        self::assertSame([['id' => 1]], $result->fetchAll());
    }

    public function testApplyShadowWritesTheMutationAndAnswersWhatItCameTo(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        $impact = $executor->applyShadow(new InsertMutation('users'), new ResultSet([['id' => 1]], []), 'INSERT');

        self::assertTrue($impact->isInsertLike());
        self::assertSame([['id' => 1]], $store->get('users'));
    }

    public function testExecStatementAnswersHowManyRowsAReadStatementCameTo(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        self::assertSame(0, $executor->execStatement('SELECT 1'));
    }

    public function testRunResultSelectAndApplyShadowReadsBackWhatTheStatementWouldHaveWritten(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );
        $plan = new RewritePlan('SELECT 1', QueryKind::WRITE_SIMULATED, new InsertMutation('users'));

        $rows = $executor->runResultSelectAndApplyShadow(
            $plan,
            static fn (string $sql): FakeStatement => new FakeStatement([['id' => 1]]),
        );

        self::assertSame([['id' => 1]], $rows);
    }

    public function testEnableTurnsZtdBackOn(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );
        $executor->session()->disable();

        $executor->session()->enable();

        self::assertTrue($executor->session()->isEnabled());
    }

    public function testRollBackTransactionPutsTheShadowBackToWhereItBegan(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        $executor->session()->transactions()->begin();
        $store->set('users', []);
        $executor->session()->transactions()->rollBack();

        self::assertSame([['id' => 1]], $store->get('users'));
    }

    public function testResultColumnTypeResolverAnswersTheOneTheSessionWasBuiltWith(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        self::assertInstanceOf(MissingResultColumnTypeResolver::class, $executor->platform()->resultColumnTypeResolver());
    }

    public function testTransactionsAnswersWhatATransactionStatementIsAppliedTo(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $registry = new TableDefinitionRegistry();
        $executor = \Tests\Fake\QueryExecutorBuilder::create(
            new FakeSqlRewriter($store, $registry),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        TransactionStatement::begin()->apply($executor->session()->transactions());
        $store->set('users', []);
        TransactionStatement::rollback()->apply($executor->session()->transactions());

        self::assertSame([['id' => 1]], $store->get('users'));
    }

    public function testPlatformAndSessionKeepReusedDialectStateIsolated(): void
    {
        $platform = new \Tests\Fake\FakePlatform(new \Tests\Fake\FakeSchemaReflector([
            'users' => 'CREATE TABLE users (id INT NOT NULL PRIMARY KEY)',
        ]));
        $first = new QueryExecutor(new FakeConnection(defaultRows: [['id' => 1]]), $platform);
        $second = new QueryExecutor(new FakeConnection(defaultRows: [['id' => 2]]), $platform);
        self::assertSame($platform, $first->platform());
        self::assertSame($platform, $second->platform());
        self::assertNotSame($first->session(), $second->session());
        self::assertNotSame($first->session()->registry(), $second->session()->registry());
        $first->session()->beginTransaction();
        $first->execStatement('INSERT INTO users (id) VALUES (1)');
        $second->execStatement('INSERT INTO users (id) VALUES (2)');
        $first->session()->rollBackTransaction();
        self::assertSame([], $first->session()->store()->get('users'));
        self::assertSame([['id' => 2]], $second->session()->store()->get('users'));
        self::assertSame('2', $second->session()->lastInsertId());
    }

    public function testSessionAcceptsAnExplicitCatalogWithoutReflectingThePhysicalConnection(): void
    {
        $connection = self::createMock(\ZtdQuery\Connection\ConnectionInterface::class);
        $connection->expects(self::never())->method('query');
        $session = new \ZtdQuery\Session();
        $session->registry()->register('users', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $session->store()->set('users', [['id' => 7]]);
        $executor = new QueryExecutor($connection, new \Tests\Fake\FakePlatform(), session: $session);
        self::assertSame($session, $executor->session());
        self::assertStringContainsString('7', $executor->rewrite('SELECT * FROM users')->sql());
    }

    public function testSessionReflectionFailurePreventsRewriterConstruction(): void
    {
        $platform = self::createMock(\ZtdQuery\Platform::class);
        $platform->expects(self::once())->method('reflectSchema')->willThrowException(new DatabaseException('catalog unavailable'));
        $platform->expects(self::never())->method('createRewriter');
        $this->expectException(DatabaseException::class);
        new QueryExecutor(new FakeConnection(), $platform);
    }
}
