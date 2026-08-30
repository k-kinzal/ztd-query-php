<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlRewriter;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\ResultSet;
use ZtdQuery\Schema\Key\IdentityGenerationStrategy;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\MutationImpact;
use ZtdQuery\Shadow\Mutation\Row\DeleteMutation;
use ZtdQuery\Shadow\Mutation\Row\InsertMutation;
use ZtdQuery\Shadow\ReferentialIntegrityEnforcer;
use ZtdQuery\Shadow\Row\RowPairing;
use ZtdQuery\Shadow\ShadowApplication;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ShadowApplication::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(ReferentialIntegrityEnforcer::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(InsertMutation::class)]
#[UsesClass(DeleteMutation::class)]
#[UsesClass(MutationImpact::class)]
#[UsesClass(ResultSet::class)]
#[UsesClass(DatabaseException::class)]
#[UsesClass(\ZtdQuery\Exception\DuplicateKeyException::class)]
#[UsesClass(\ZtdQuery\Schema\Key\CandidateKeyConflict::class)]
#[UsesClass(\ZtdQuery\Schema\Key\CandidateKeyMatch::class)]
#[UsesClass(\ZtdQuery\Schema\Key\CandidateKeySet::class)]
#[UsesClass(\ZtdQuery\Shadow\ForeignKeyCascade::class)]
#[UsesClass(\ZtdQuery\Shadow\ForeignKeyEnds::class)]
#[UsesClass(\ZtdQuery\Shadow\ForeignKeyIntegrity::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\ConflictSearch::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\RowConstraints::class)]
#[UsesClass(\ZtdQuery\Shadow\ParentKeyLookup::class)]
#[UsesClass(\ZtdQuery\Shadow\Row\RowMatch::class)]
#[UsesClass(\ZtdQuery\Shadow\Row\RowMultiset::class)]
#[UsesClass(\ZtdQuery\Shadow\Row\TableTransition::class)]
#[UsesClass(\ZtdQuery\Shadow\TableTransitions::class)]
#[UsesClass(RowPairing::class)]
final class ShadowApplicationTest extends TestCase
{
    public function testApplyWritesTheRowsAMutationDescribesIntoTheShadow(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);
        $registry = new TableDefinitionRegistry();
        $application = new ShadowApplication(
            $store,
            new ReferentialIntegrityEnforcer($registry),
            $registry,
            new FakeSqlRewriter($store, $registry),
        );

        $application->apply(new InsertMutation('users'), new ResultSet([['id' => 1]], []), 'INSERT');

        self::assertSame([['id' => 1]], $store->get('users'));
    }

    public function testApplyAnswersWhatTheStatementCameTo(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);
        $registry = new TableDefinitionRegistry();
        $application = new ShadowApplication(
            $store,
            new ReferentialIntegrityEnforcer($registry),
            $registry,
            new FakeSqlRewriter($store, $registry),
        );

        $impact = $application->apply(new InsertMutation('users'), new ResultSet([['id' => 1]], []), 'INSERT');

        self::assertTrue($impact->isInsertLike());
    }

    public function testApplyLeavesTheShadowAsItWasWhenTheStatementIsRefused(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(['id'], [], ['id'], ['id'], ['PRIMARY' => ['id']]));
        $application = new ShadowApplication(
            $store,
            new ReferentialIntegrityEnforcer($registry),
            $registry,
            new FakeSqlRewriter($store, $registry),
        );

        try {
            $application->apply(
                new InsertMutation('users', ['id'], false, $registry->get('users'), 'INSERT', true),
                new ResultSet([['id' => 1]], []),
                'INSERT',
            );
        } catch (DatabaseException) {
            self::assertSame([['id' => 1]], $store->get('users'));

            return;
        }

        self::fail('Inserting a duplicate key was expected to be refused.');
    }

    public function testLastInsertIdOfAnswersTheIdentityTheTableSaysIsNumbered(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(
            ['id'],
            [],
            ['id'],
            [],
            [],
            identityStrategies: ['id' => IdentityGenerationStrategy::MaxValue],
        ));
        $application = new ShadowApplication(
            $store,
            new ReferentialIntegrityEnforcer($registry),
            $registry,
            new FakeSqlRewriter($store, $registry),
        );
        $mutation = new InsertMutation('users');
        $impact = $application->apply($mutation, new ResultSet([['id' => 7]], []), 'INSERT');

        self::assertSame('7', $application->lastInsertIdOf($mutation, $impact));
    }

    public function testLastInsertIdOfFallsBackToASingleColumnKey(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(['id'], [], ['id'], [], []));
        $application = new ShadowApplication(
            $store,
            new ReferentialIntegrityEnforcer($registry),
            $registry,
            new FakeSqlRewriter($store, $registry),
        );
        $mutation = new InsertMutation('users');
        $impact = $application->apply($mutation, new ResultSet([['id' => 7]], []), 'INSERT');

        self::assertSame('7', $application->lastInsertIdOf($mutation, $impact));
    }

    public function testLastInsertIdOfIsNothingForAStatementThatInsertedNothing(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $registry = new TableDefinitionRegistry();
        $application = new ShadowApplication(
            $store,
            new ReferentialIntegrityEnforcer($registry),
            $registry,
            new FakeSqlRewriter($store, $registry),
        );
        $mutation = new DeleteMutation('users', ['id']);
        $impact = $application->apply($mutation, new ResultSet([['id' => 1]], []), 'DELETE');

        self::assertNull($application->lastInsertIdOf($mutation, $impact));
    }

    public function testLastInsertIdOfIsNothingWhereNoColumnIsNumbered(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);
        $registry = new TableDefinitionRegistry();
        $registry->register('users', new TableDefinition(['a', 'b'], [], ['a', 'b'], [], []));
        $application = new ShadowApplication(
            $store,
            new ReferentialIntegrityEnforcer($registry),
            $registry,
            new FakeSqlRewriter($store, $registry),
        );
        $mutation = new InsertMutation('users');
        $impact = $application->apply($mutation, new ResultSet([['a' => 1, 'b' => 2]], []), 'INSERT');

        self::assertNull($application->lastInsertIdOf($mutation, $impact));
    }
}
