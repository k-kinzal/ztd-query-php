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
use ZtdQuery\Exception\MissingPrimaryKeyException;
use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Platform\CopySupport;
use ZtdQuery\Platform\CopyTarget;
use ZtdQuery\Platform\SqlPlaceholderEscaper;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Schema\CandidateKeySet;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Session;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\ReferentialIntegrityEnforcer;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactionManager;

#[CoversClass(Session::class)]
#[UsesClass(ZtdConfig::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(ShadowTransactionManager::class)]
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
final class SessionTest extends TestCase
{
    public function testEnableAndDisable(): void
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
        self::assertNull($session->sqlPlaceholderEscaper());

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

    public function testDelegatesCopyTargetsToTheConfiguredPlatformSupport(): void
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
        $escaper = self::createStub(SqlPlaceholderEscaper::class);
        $session = new Session(
            new FakeSqlRewriter($shadowStore, $registry),
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new FakeConnection(),
            registry: $registry,
            copySupport: $copy,
            sqlPlaceholderEscaper: $escaper,
        );

        self::assertSame($copy, $session->copySupport());
        self::assertSame($target, $session->copyTarget('public.users', 'id'));
        self::assertNull($session->copyTarget('missing', null));
        self::assertSame($escaper, $session->sqlPlaceholderEscaper());
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

    public function testUsesProvidedTransactionManagerForSchemaRollback(): void
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
            new ShadowTransactionManager($shadowStore, $registry),
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
}
