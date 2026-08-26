<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeConnection;
use Tests\Fake\FakeSqlRewriter;
use Tests\Fake\FakeStatement;
use Tests\Fake\SessionUnderTest;
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
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Schema\CandidateKeySet;
use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Session;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\ReferentialIntegrityEnforcer;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactions;
use ZtdQuery\Sql\TransactionStatement;

#[CoversClass(Session::class)]
#[UsesClass(ZtdConfig::class)]
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
final class SessionTest extends TestCase
{
    public function testDisableEnableDisableEnableAndDisable(): void
    {
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $rewriter = new FakeSqlRewriter($shadowStore, $registry);
        $connection = new FakeConnection();
        $session = new Session(
            $rewriter,
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            $connection,
        );

        self::assertTrue($session->isEnabled());
        self::assertNull($session->tableDefinition('users'));
        self::assertNull($session->copySupport());
        self::assertNull($session->copyTarget('users', null));
        self::assertNull($session->parameterBindingCompiler());
        self::assertInstanceOf(MissingResultColumnTypeResolver::class, $session->resultColumnTypeResolver());

        $session->disable();
        self::assertFalse($session->isEnabled());

        $session->enable();
        self::assertTrue($session->isEnabled());
    }

    public function testTableDefinitionReturnsRegisteredSchemaOrNull(): void
    {
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], ['id'], []);
        $registry->register('users', $definition);
        $session = new Session(
            new FakeSqlRewriter($shadowStore, $registry),
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
        );

        self::assertSame($definition, $session->tableDefinition('users'));
        self::assertNull($session->tableDefinition('missing'));
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
        $session = new Session(
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

        self::assertSame($copy, $session->copySupport());
        self::assertSame($target, $session->copyTarget('public.users', 'id'));
        self::assertNull($session->copyTarget('missing', null));
        self::assertSame($compiler, $session->parameterBindingCompiler());
        self::assertSame($typeResolver, $session->resultColumnTypeResolver());
    }

    public function testSplitStatementsUsesPlatformRewriter(): void
    {
        $shadowStore = new ShadowStore();
        $rewriter = new FakeSqlRewriter($shadowStore, new TableDefinitionRegistry());
        $session = new Session(
            $rewriter,
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
        );

        self::assertSame(
            ['SELECT 1', 'SELECT 2'],
            $session->splitStatements(' SELECT 1; SELECT 2 '),
        );
    }

    public function testBeginTransactionRollBackTransactionBeginTransactionUsesProvidedTransactionManagerForSchemaRollback(): void
    {
        $shadowStore = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INT'], ['id'], [], []);
        $registry->register('users', $definition);
        $session = new Session(
            new FakeSqlRewriter($shadowStore, $registry),
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            new ShadowTransactions($shadowStore, $registry),
        );

        $session->beginTransaction();
        $registry->unregister('users');
        $session->rollBackTransaction();

        self::assertSame($definition, $registry->get('users'));
    }

    public function testMutationFailureIsConvertedToDatabaseException(): void
    {
        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $registry = new TableDefinitionRegistry();
        $session = new Session(
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
            $session->processExecutedStatement($plan, new FakeStatement([['id' => 1, 'name' => 'Bob']]));
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
        $session = new Session(
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
            $session->processExecutedStatement($plan, new FakeStatement([['id' => 1, 'parent_id' => 999]]));
            self::fail('Expected a database exception.');
        } catch (DatabaseException $exception) {
            self::assertInstanceOf(ForeignKeyViolationException::class, $exception->getPrevious());
            self::assertSame([], $shadowStore->get('children'));
        }
    }

    public function testIsEnabledFollowsWhatWasAskedFor(): void
    {
        $session = SessionUnderTest::plain();

        self::assertTrue($session->isEnabled());

        $session->disable();

        self::assertFalse($session->isEnabled());
    }

    public function testDisableStopsZtdWithoutTouchingTheShadow(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $session = SessionUnderTest::over($store);

        $session->disable();

        self::assertSame([['id' => 1]], $store->get('users'));
    }

    public function testShouldExecuteIsFalseOnlyForAPlanNothingIsToBeRunFor(): void
    {
        $session = SessionUnderTest::plain();

        self::assertTrue($session->shouldExecute(new RewritePlan('SELECT 1', QueryKind::READ)));
        self::assertFalse($session->shouldExecute(new RewritePlan('SELECT 1', QueryKind::SKIPPED)));
    }

    public function testNeedsPostProcessingIsTrueForTheKindsThatChangeTheShadow(): void
    {
        $session = SessionUnderTest::plain();

        self::assertTrue($session->needsPostProcessing(new RewritePlan('x', QueryKind::WRITE_SIMULATED)));
        self::assertTrue($session->needsPostProcessing(new RewritePlan('x', QueryKind::DDL_SIMULATED)));
        self::assertFalse($session->needsPostProcessing(new RewritePlan('x', QueryKind::READ)));
    }

    public function testCreateEmptyWriteResultAnswersASimulatedWriteWithNothingToFetch(): void
    {
        $result = SessionUnderTest::plain()->createEmptyWriteResult();

        self::assertSame(QueryKind::WRITE_SIMULATED, $result->kind());
        self::assertSame([], $result->fetchAll());
    }

    public function testLastInsertIdIsFalseUntilSomethingHasBeenInserted(): void
    {
        self::assertFalse(SessionUnderTest::plain()->lastInsertId());
    }

    public function testTransactionStatementIsNothingForAStatementThatIsNotOne(): void
    {
        self::assertNull(SessionUnderTest::plain()->transactionStatement('SELECT 1'));
    }

    public function testCommitTransactionKeepsWhatTheTransactionDid(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $session = SessionUnderTest::over($store);

        $session->beginTransaction();
        $store->set('users', []);
        $session->commitTransaction();
        $session->rollBackTransaction();

        self::assertSame([], $store->get('users'));
    }

    public function testApplyTransactionStatementDoesWhatTheStatementSays(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $session = SessionUnderTest::over($store);

        $session->applyTransactionStatement(TransactionStatement::begin());
        $store->set('users', []);
        $session->applyTransactionStatement(TransactionStatement::rollback());

        self::assertSame([['id' => 1]], $store->get('users'));
    }

    public function testCopySupportIsNothingWhereTheDialectHasNoCopy(): void
    {
        self::assertNull(SessionUnderTest::plain()->copySupport());
    }

    public function testCopyTargetIsNothingWhereTheDialectHasNoCopy(): void
    {
        self::assertNull(SessionUnderTest::plain()->copyTarget('users', null));
    }

    public function testParameterBindingCompilerIsNothingWhereTheDriverBindsThemItself(): void
    {
        self::assertNull(SessionUnderTest::plain()->parameterBindingCompiler());
    }

    public function testRewriteAnswersThePlanTheRewriterGives(): void
    {
        $plan = SessionUnderTest::plain()->rewrite('SELECT 1');

        self::assertSame(QueryKind::READ, $plan->kind());
    }

    public function testProcessExecutedStatementReadsAReadStatementStraightBack(): void
    {
        $session = SessionUnderTest::plain();
        $plan = new RewritePlan('SELECT 1', QueryKind::READ);

        $result = $session->processExecutedStatement($plan, new FakeStatement([['id' => 1]]));

        self::assertSame([['id' => 1]], $result->fetchAll());
    }

    public function testApplyShadowWritesTheMutationAndAnswersWhatItCameTo(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);
        $session = SessionUnderTest::over($store);

        $impact = $session->applyShadow(new InsertMutation('users'), new ResultSet([['id' => 1]], []), 'INSERT');

        self::assertTrue($impact->isInsertLike());
        self::assertSame([['id' => 1]], $store->get('users'));
    }

    public function testExecStatementAnswersHowManyRowsAReadStatementCameTo(): void
    {
        $session = SessionUnderTest::plain();

        self::assertSame(0, $session->execStatement('SELECT 1'));
    }

    public function testRunResultSelectAndApplyShadowReadsBackWhatTheStatementWouldHaveWritten(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);
        $session = SessionUnderTest::over($store);
        $plan = new RewritePlan('SELECT 1', QueryKind::WRITE_SIMULATED, new InsertMutation('users'));

        $rows = $session->runResultSelectAndApplyShadow(
            $plan,
            static fn (string $sql): FakeStatement => new FakeStatement([['id' => 1]]),
        );

        self::assertSame([['id' => 1]], $rows);
    }

    public function testEnableTurnsZtdBackOn(): void
    {
        $session = SessionUnderTest::plain();
        $session->disable();

        $session->enable();

        self::assertTrue($session->isEnabled());
    }

    public function testRollBackTransactionPutsTheShadowBackToWhereItBegan(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $session = SessionUnderTest::over($store);

        $session->beginTransaction();
        $store->set('users', []);
        $session->rollBackTransaction();

        self::assertSame([['id' => 1]], $store->get('users'));
    }

    public function testResultColumnTypeResolverAnswersTheOneTheSessionWasBuiltWith(): void
    {
        $session = SessionUnderTest::plain();

        self::assertInstanceOf(MissingResultColumnTypeResolver::class, $session->resultColumnTypeResolver());
    }
}
