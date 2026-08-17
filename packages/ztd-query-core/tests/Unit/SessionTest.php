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
use ZtdQuery\Exception\MissingPrimaryKeyException;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Session;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(Session::class)]
#[UsesClass(ZtdConfig::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(ResultSelectRunner::class)]
#[UsesClass(DatabaseException::class)]
#[UsesClass(RewritePlan::class)]
#[UsesClass(UpdateMutation::class)]
#[UsesClass(MutationRowIdentity::class)]
#[UsesClass(MissingPrimaryKeyException::class)]
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

        $session->disable();
        self::assertFalse($session->isEnabled());

        $session->enable();
        self::assertTrue($session->isEnabled());
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
}
