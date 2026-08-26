<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Schema\CandidateKeySet;
use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Schema\ReferentialAction;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\MultiTruncateMutation;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\Mutation\SynchronizeMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\ReferentialIntegrityEnforcer;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ReferentialIntegrityEnforcer::class)]
#[UsesClass(ForeignKeyViolationException::class)]
#[UsesClass(CandidateKeySet::class)]
#[UsesClass(ForeignKeyDefinition::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(DeleteMutation::class)]
#[UsesClass(CreateTableMutation::class)]
#[UsesClass(InsertMutation::class)]
#[UsesClass(MultiTruncateMutation::class)]
#[UsesClass(SynchronizeMutation::class)]
#[UsesClass(UpdateMutation::class)]
#[UsesClass(MutationRowIdentity::class)]
#[UsesClass(ShadowStore::class)]
final class ReferentialIntegrityEnforcerTest extends TestCase
{
    public function testSchemaMutationSkipsRowIntegrityChecks(): void
    {
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
        $store = new ShadowStore();
        $store->set('parents', []);
        $store->set('children', [['id' => 1, 'parent_id' => 99]]);
        $mutation = new class () implements ShadowMutation {
            public function apply(ShadowStore $store, array $rows): void
            {
            }

            public function tableName(): string
            {
                return 'children';
            }
        };

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $store->snapshot(),
            $store,
            $mutation,
            [],
            'CREATE TABLE',
        );

        self::assertSame([['id' => 1, 'parent_id' => 99]], $store->get('children'));
    }

    public function testSynchronizeCascadesDeletedRows(): void
    {
        $registry = new TableDefinitionRegistry();
        $parent = new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []);
        $registry->register('parents', $parent);
        $registry->register('children', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_id'],
                'parents',
                ['id'],
                onDelete: ReferentialAction::Cascade,
            )],
        ));
        $store = new ShadowStore();
        $store->set('parents', [['id' => 1]]);
        $store->set('children', [['id' => 10, 'parent_id' => 1]]);
        $before = $store->snapshot();
        $mutation = new SynchronizeMutation('parents', $parent);
        $mutation->apply($store, []);

        (new ReferentialIntegrityEnforcer($registry))->synchronize($before, $store, $mutation, [], 'MERGE');

        self::assertSame([], $store->get('children'));
    }

    public function testRejectsInsertWhoseParentDoesNotExist(): void
    {
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
        $store = new ShadowStore();
        $store->set('parents', []);
        $store->set('children', []);
        $before = $store->snapshot();
        $mutation = new InsertMutation('children');
        $mutation->apply($store, [['id' => 1, 'parent_id' => 99]]);

        $this->expectException(ForeignKeyViolationException::class);
        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $store,
            $mutation,
            [['id' => 1, 'parent_id' => 99]],
            'INSERT',
        );
    }

    public function testAcceptsExplicitReferenceToNonPrimaryCandidateKey(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(
            ['id', 'code'],
            ['id' => 'INT', 'code' => 'TEXT'],
            ['id'],
            ['id', 'code'],
            ['parents_code_key' => ['code']],
        ));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_code'],
            ['id' => 'INT', 'parent_code' => 'TEXT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent_code' => new ForeignKeyDefinition(
                ['parent_code'],
                'parents',
                ['code'],
            )],
        ));
        $store = new ShadowStore();
        $store->set('parents', [['id' => 1, 'code' => 'parent-a']]);
        $store->set('children', []);
        $before = $store->snapshot();
        $mutation = new InsertMutation('children');
        $mutation->apply($store, [['id' => 10, 'parent_code' => 'parent-a']]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $store,
            $mutation,
            [['id' => 10, 'parent_code' => 'parent-a']],
            'INSERT',
        );

        self::assertSame([['id' => 10, 'parent_code' => 'parent-a']], $store->get('children'));
    }

    public function testDeleteCascadePropagatesAcrossMultipleLevels(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('departments', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('employees', new TableDefinition(
            ['id', 'department_id'],
            ['id' => 'INT', 'department_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_department' => new ForeignKeyDefinition(
                ['department_id'],
                'departments',
                ['id'],
                ReferentialAction::Cascade,
            )],
        ));
        $registry->register('tasks', new TableDefinition(
            ['id', 'employee_id'],
            ['id' => 'INT', 'employee_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_employee' => new ForeignKeyDefinition(
                ['employee_id'],
                'employees',
                ['id'],
                ReferentialAction::Cascade,
            )],
        ));
        $store = new ShadowStore();
        $store->set('departments', [['id' => 1]]);
        $store->set('employees', [['id' => 10, 'department_id' => 1]]);
        $store->set('tasks', [['id' => 100, 'employee_id' => 10]]);
        $before = $store->snapshot();
        $mutation = new DeleteMutation('departments', ['id']);
        $mutation->apply($store, [['id' => 1]]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $store,
            $mutation,
            [['id' => 1]],
            'DELETE',
        );

        self::assertSame([], $store->get('departments'));
        self::assertSame([], $store->get('employees'));
        self::assertSame([], $store->get('tasks'));
    }

    public function testPrimaryKeyUpdateCascadesThroughOriginalRowIdentity(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_id'],
                'parents',
                ['id'],
                onUpdate: ReferentialAction::Cascade,
            )],
        ));
        $store = new ShadowStore();
        $store->set('parents', [['id' => 1]]);
        $store->set('children', [['id' => 10, 'parent_id' => 1]]);
        $before = $store->snapshot();
        $mutation = new UpdateMutation('parents', ['id']);
        $resultRows = [['id' => 2, '__ztd_original_id' => 1]];
        $mutation->apply($store, $resultRows);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $store,
            $mutation,
            $resultRows,
            'UPDATE',
        );

        self::assertSame([['id' => 2]], $store->get('parents'));
        self::assertSame([['id' => 10, 'parent_id' => 2]], $store->get('children'));
    }

    public function testDeleteSetNullUpdatesChild(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_id'],
                'parents',
                ['id'],
                ReferentialAction::SetNull,
            )],
        ));
        $store = new ShadowStore();
        $store->set('parents', [['id' => 1]]);
        $store->set('children', [['id' => 10, 'parent_id' => 1]]);
        $before = $store->snapshot();
        $mutation = new DeleteMutation('parents', ['id']);
        $mutation->apply($store, [['id' => 1]]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $store,
            $mutation,
            [['id' => 1]],
            'DELETE',
        );

        self::assertSame([['id' => 10, 'parent_id' => null]], $store->get('children'));
    }

    public function testDeleteRestrictRejectsParentRemoval(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_id'],
                'parents',
                ['id'],
                ReferentialAction::Restrict,
            )],
        ));
        $store = new ShadowStore();
        $store->set('parents', [['id' => 2]]);
        $store->set('children', [['id' => 20, 'parent_id' => 2]]);
        $before = $store->snapshot();
        $mutation = new DeleteMutation('parents', ['id']);
        $mutation->apply($store, [['id' => 2]]);

        $this->expectException(ForeignKeyViolationException::class);
        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $store,
            $mutation,
            [['id' => 2]],
            'DELETE',
        );
    }

    public function testSchemaMutationDoesNotTriggerRowValidation(): void
    {
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
        $store = new ShadowStore();
        $store->set('parents', []);
        $store->set('children', [['id' => 1, 'parent_id' => 99]]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $store->snapshot(),
            $store,
            new CreateTableMutation('archive', null, $registry, 'fixture statement'),
            [],
            'CREATE TABLE archive',
        );

        self::assertSame([['id' => 1, 'parent_id' => 99]], $store->get('children'));
    }

    public function testMultiTruncateCascadesFromEveryChangedParentTable(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parent_a', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('parent_b', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('child_a', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_a' => new ForeignKeyDefinition(
                ['parent_id'],
                'parent_a',
                ['id'],
                ReferentialAction::Cascade,
            )],
        ));
        $registry->register('child_b', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_b' => new ForeignKeyDefinition(
                ['parent_id'],
                'parent_b',
                ['id'],
                ReferentialAction::Cascade,
            )],
        ));
        $store = new ShadowStore();
        $store->set('parent_a', [['id' => 1]]);
        $store->set('parent_b', [['id' => 2]]);
        $store->set('child_a', [['id' => 10, 'parent_id' => 1]]);
        $store->set('child_b', [['id' => 20, 'parent_id' => 2]]);
        $before = $store->snapshot();
        $mutation = new MultiTruncateMutation(['parent_a', 'parent_b']);
        $mutation->apply($store, []);

        (new ReferentialIntegrityEnforcer($registry))->synchronize($before, $store, $mutation, [], 'TRUNCATE');

        self::assertSame([], $store->get('parent_a'));
        self::assertSame([], $store->get('parent_b'));
        self::assertSame([], $store->get('child_a'));
        self::assertSame([], $store->get('child_b'));
    }

    public function testMatchingConstraintAfterUnrelatedConstraintStillCascades(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parent_a', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('parent_b', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('children', new TableDefinition(
            ['id', 'a_id', 'b_id'],
            ['id' => 'INT', 'a_id' => 'INT', 'b_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: [
                'fk_a' => new ForeignKeyDefinition(['a_id'], 'parent_a', ['id']),
                'fk_b' => new ForeignKeyDefinition(['b_id'], 'parent_b', ['id'], ReferentialAction::Cascade),
            ],
        ));
        $store = new ShadowStore();
        $store->set('parent_a', [['id' => 1]]);
        $store->set('parent_b', [['id' => 2]]);
        $store->set('children', [['id' => 10, 'a_id' => 1, 'b_id' => 2]]);
        $before = $store->snapshot();
        $mutation = new DeleteMutation('parent_b', ['id']);
        $mutation->apply($store, [['id' => 2]]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize($before, $store, $mutation, [], 'DELETE');

        self::assertSame([], $store->get('children'));
    }

    public function testCompositePrimaryKeyUpdateCascadesOnlyMatchingChildren(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(
            ['tenant_id', 'id'],
            ['tenant_id' => 'INT', 'id' => 'INT'],
            ['tenant_id', 'id'],
            ['tenant_id', 'id'],
            [],
        ));
        $registry->register('children', new TableDefinition(
            ['id', 'tenant_id', 'parent_id'],
            ['id' => 'INT', 'tenant_id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['tenant_id', 'parent_id'],
                'parents',
                ['tenant_id', 'id'],
                onUpdate: ReferentialAction::Cascade,
            )],
        ));
        $store = new ShadowStore();
        $store->set('parents', [['tenant_id' => 7, 'id' => 1], ['tenant_id' => 7, 'id' => 2]]);
        $store->set('children', [
            ['id' => 10, 'tenant_id' => 7, 'parent_id' => 1],
            ['id' => 20, 'tenant_id' => 7, 'parent_id' => 2],
        ]);
        $before = $store->snapshot();
        $mutation = new UpdateMutation('parents', ['tenant_id', 'id']);
        $resultRows = [[
            'tenant_id' => 8,
            'id' => 3,
            '__ztd_original_tenant_id' => 7,
            '__ztd_original_id' => 1,
        ]];
        $mutation->apply($store, $resultRows);

        (new ReferentialIntegrityEnforcer($registry))->synchronize($before, $store, $mutation, $resultRows, 'UPDATE');

        self::assertSame([['tenant_id' => 8, 'id' => 3], ['tenant_id' => 7, 'id' => 2]], $store->get('parents'));
        self::assertSame([
            ['id' => 10, 'tenant_id' => 8, 'parent_id' => 3],
            ['id' => 20, 'tenant_id' => 7, 'parent_id' => 2],
        ], $store->get('children'));
    }

    public function testUpdateDoesNotCascadeWhileOldParentKeyStillExists(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_id'],
                'parents',
                ['id'],
                onUpdate: ReferentialAction::Cascade,
            )],
        ));
        $before = new ShadowStore();
        $before->set('parents', [['id' => 1, 'name' => 'updated'], ['id' => 1, 'name' => 'retained']]);
        $before->set('children', [['id' => 10, 'parent_id' => 1]]);
        $after = $before->snapshot();
        $after->set('parents', [['id' => 2, 'name' => 'updated'], ['id' => 1, 'name' => 'retained']]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $after,
            new UpdateMutation('parents', ['id']),
            [['id' => 2, 'name' => 'updated', '__ztd_original_id' => 1]],
            'UPDATE',
        );

        self::assertSame([['id' => 10, 'parent_id' => 1]], $after->get('children'));
    }

    public function testExplicitReferencedColumnsAreNotReplacedByPrimaryKey(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(
            ['id', 'code'],
            ['id' => 'TEXT', 'code' => 'TEXT'],
            ['id'],
            ['id', 'code'],
            ['uq_code' => ['code']],
        ));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_code'],
            ['id' => 'INT', 'parent_code' => 'TEXT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_code' => new ForeignKeyDefinition(['parent_code'], 'parents', ['code'])],
        ));
        $store = new ShadowStore();
        $store->set('parents', [['id' => 'wrong-key', 'code' => 'expected-code']]);
        $store->set('children', []);
        $before = $store->snapshot();
        $mutation = new InsertMutation('children');
        $mutation->apply($store, [['id' => 1, 'parent_code' => 'expected-code']]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize($before, $store, $mutation, [], 'INSERT');

        self::assertSame([['id' => 1, 'parent_code' => 'expected-code']], $store->get('children'));
    }

    public function testValidationContinuesPastMissingAndNullForeignKeys(): void
    {
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
        $store = new ShadowStore();
        $store->set('parents', [['id' => 1]]);
        $store->set('children', []);
        $before = $store->snapshot();
        $rows = [['id' => 10], ['id' => 20, 'parent_id' => null], ['id' => 30, 'parent_id' => 99]];
        $mutation = new InsertMutation('children');
        $mutation->apply($store, $rows);

        $this->expectException(ForeignKeyViolationException::class);
        (new ReferentialIntegrityEnforcer($registry))->synchronize($before, $store, $mutation, $rows, 'INSERT');
    }

    public function testCompositeDeleteSetNullNormalizesEveryForeignKeyColumn(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(
            ['tenant_id', 'id'],
            ['tenant_id' => 'INT', 'id' => 'INT'],
            ['tenant_id', 'id'],
            ['tenant_id', 'id'],
            [],
        ));
        $registry->register('children', new TableDefinition(
            ['id', 'tenant_id', 'parent_id'],
            ['id' => 'INT', 'tenant_id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['tenant_id', 'parent_id'],
                'parents',
                ['tenant_id', 'id'],
                ReferentialAction::SetNull,
            )],
        ));
        $store = new ShadowStore();
        $store->set('parents', [['tenant_id' => 7, 'id' => 1], ['tenant_id' => 7, 'id' => 2]]);
        $store->set('children', [
            ['id' => 10, 'tenant_id' => 7, 'parent_id' => 1],
            ['id' => 20, 'tenant_id' => 7, 'parent_id' => 1],
            ['id' => 30, 'tenant_id' => 7, 'parent_id' => 2],
        ]);
        $before = $store->snapshot();
        $mutation = new DeleteMutation('parents', ['tenant_id', 'id']);
        $mutation->apply($store, [['tenant_id' => 7, 'id' => 1]]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize($before, $store, $mutation, [], 'DELETE');

        self::assertSame([
            ['id' => 10, 'tenant_id' => null, 'parent_id' => null],
            ['id' => 20, 'tenant_id' => null, 'parent_id' => null],
            ['id' => 30, 'tenant_id' => 7, 'parent_id' => 2],
        ], $store->get('children'));
    }

    public function testDeleteCascadeRemovesAllMatchingChildrenAndKeepsOthers(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_id'],
                'parents',
                ['id'],
                ReferentialAction::Cascade,
            )],
        ));
        $store = new ShadowStore();
        $store->set('parents', [['id' => 1], ['id' => 2]]);
        $store->set('children', [
            ['id' => 10, 'parent_id' => 1],
            ['id' => 20, 'parent_id' => 1],
            ['id' => 30, 'parent_id' => 2],
        ]);
        $before = $store->snapshot();
        $mutation = new DeleteMutation('parents', ['id']);
        $mutation->apply($store, [['id' => 1]]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize($before, $store, $mutation, [], 'DELETE');

        self::assertSame([['id' => 30, 'parent_id' => 2]], $store->get('children'));
    }

    public function testFallbackReferencedPrimaryKeyAppearsInUpdateViolation(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(['parent_id'], 'parents', [])],
        ));
        $before = new ShadowStore();
        $before->set('parents', [['id' => 1]]);
        $before->set('children', [['id' => 10, 'parent_id' => 1]]);
        $after = $before->snapshot();
        $after->set('parents', [['id' => 2]]);

        $this->expectException(ForeignKeyViolationException::class);
        $this->expectExceptionMessage("referenced row not found in 'parents.id'");
        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $after,
            new UpdateMutation('parents', ['id']),
            [['id' => 2, '__ztd_original_id' => 1]],
            'UPDATE',
        );
    }

    public function testFallbackTransitionPropagatesUpdateAndDeleteFromOneTable(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(
            ['id', 'code'],
            ['id' => 'INT', 'code' => 'TEXT'],
            ['id'],
            ['id', 'code'],
            ['uq_code' => ['code']],
        ));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_code'],
            ['id' => 'INT', 'parent_code' => 'TEXT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_code'],
                'parents',
                ['code'],
                ReferentialAction::Cascade,
                ReferentialAction::Cascade,
            )],
        ));
        $before = new ShadowStore();
        $before->set('parents', [
            ['id' => 2, 'code' => 'b'],
            ['id' => 1, 'code' => 'a'],
            ['id' => 3, 'code' => 'c'],
        ]);
        $before->set('children', [
            ['id' => 30, 'parent_code' => 'c'],
            ['id' => 10, 'parent_code' => 'a'],
            ['id' => 20, 'parent_code' => 'b'],
        ]);
        $after = $before->snapshot();
        $after->set('parents', [
            ['id' => 1, 'code' => 'aa'],
            ['id' => 3, 'code' => 'c'],
        ]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $after,
            new InsertMutation('unrelated'),
            [],
            'CHANGE',
        );

        self::assertSame([
            ['id' => 30, 'parent_code' => 'c'],
            ['id' => 10, 'parent_code' => 'aa'],
        ], $after->get('children'));
    }

    public function testIdentityTransitionSkipsIncompleteChangeAndProcessesFollowingChange(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_id'],
                'parents',
                ['id'],
                onUpdate: ReferentialAction::Cascade,
            )],
        ));
        $before = new ShadowStore();
        $before->set('parents', [['id' => 1], ['id' => 2]]);
        $before->set('children', [['id' => 10, 'parent_id' => 1], ['id' => 20, 'parent_id' => 2]]);
        $after = $before->snapshot();
        $after->set('parents', [['id' => 1], ['id' => 20]]);
        $resultRows = [
            ['id' => 99, '__ztd_original_id' => 1],
            ['id' => 20, '__ztd_original_id' => 2],
        ];

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $after,
            new UpdateMutation('parents', ['id']),
            $resultRows,
            'UPDATE',
        );

        self::assertSame([
            ['id' => 10, 'parent_id' => 1],
            ['id' => 20, 'parent_id' => 20],
        ], $after->get('children'));
    }

    public function testRetainedOldKeyDoesNotStopFollowingUpdateCascade(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(
            ['id', 'code'],
            ['id' => 'INT', 'code' => 'TEXT'],
            ['id'],
            ['id', 'code'],
            [],
        ));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_code'],
            ['id' => 'INT', 'parent_code' => 'TEXT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_code'],
                'parents',
                ['code'],
                onUpdate: ReferentialAction::Cascade,
            )],
        ));
        $before = new ShadowStore();
        $before->set('parents', [
            ['id' => 1, 'code' => 'a'],
            ['id' => 2, 'code' => 'a'],
            ['id' => 3, 'code' => 'b'],
        ]);
        $before->set('children', [
            ['id' => 10, 'parent_code' => 'a'],
            ['id' => 20, 'parent_code' => 'b'],
        ]);
        $after = $before->snapshot();
        $after->set('parents', [
            ['id' => 1, 'code' => 'c'],
            ['id' => 2, 'code' => 'a'],
            ['id' => 3, 'code' => 'd'],
        ]);
        $resultRows = [
            ['id' => 1, 'code' => 'c', '__ztd_original_id' => 1],
            ['id' => 3, 'code' => 'd', '__ztd_original_id' => 3],
        ];

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $after,
            new UpdateMutation('parents', ['id']),
            $resultRows,
            'UPDATE',
        );

        self::assertSame([
            ['id' => 10, 'parent_code' => 'a'],
            ['id' => 20, 'parent_code' => 'd'],
        ], $after->get('children'));
    }

    public function testMissingReferencedValuesDoNotStopLaterUpdateOrDeleteCascades(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(
            ['id', 'code'],
            ['id' => 'INT', 'code' => 'TEXT'],
            ['id'],
            ['id'],
            [],
        ));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_code'],
            ['id' => 'INT', 'parent_code' => 'TEXT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_code'],
                'parents',
                ['code'],
                ReferentialAction::Cascade,
                ReferentialAction::Cascade,
            )],
        ));
        $beforeUpdate = new ShadowStore();
        $beforeUpdate->set('parents', [['id' => 1], ['id' => 2, 'code' => 'b']]);
        $beforeUpdate->set('children', [['id' => 20, 'parent_code' => 'b']]);
        $afterUpdate = $beforeUpdate->snapshot();
        $afterUpdate->set('parents', [['id' => 1, 'code' => 'x'], ['id' => 2, 'code' => 'c']]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $beforeUpdate,
            $afterUpdate,
            new InsertMutation('unrelated'),
            [],
            'UPDATE',
        );

        self::assertSame([['id' => 20, 'parent_code' => 'c']], $afterUpdate->get('children'));

        $beforeDelete = new ShadowStore();
        $beforeDelete->set('parents', [['id' => 1], ['id' => 2, 'code' => 'b']]);
        $beforeDelete->set('children', [['id' => 20, 'parent_code' => 'b']]);
        $afterDelete = $beforeDelete->snapshot();
        $afterDelete->set('parents', []);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $beforeDelete,
            $afterDelete,
            new InsertMutation('unrelated'),
            [],
            'DELETE',
        );

        self::assertSame([], $afterDelete->get('children'));
    }

    public function testUpdateCascadeCarriesOriginalRowIntoNextLevel(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_id'],
                'parents',
                ['id'],
                onUpdate: ReferentialAction::Cascade,
            )],
        ));
        $registry->register('grandchildren', new TableDefinition(
            ['id', 'child_parent_id'],
            ['id' => 'INT', 'child_parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_child' => new ForeignKeyDefinition(
                ['child_parent_id'],
                'children',
                ['parent_id'],
                onUpdate: ReferentialAction::Cascade,
            )],
        ));
        $before = new ShadowStore();
        $before->set('parents', [['id' => 1]]);
        $before->set('children', [['id' => 10, 'parent_id' => 1]]);
        $before->set('grandchildren', [['id' => 100, 'child_parent_id' => 1]]);
        $after = $before->snapshot();
        $after->set('parents', [['id' => 2]]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $after,
            new UpdateMutation('parents', ['id']),
            [['id' => 2, '__ztd_original_id' => 1]],
            'UPDATE',
        );

        self::assertSame([['id' => 10, 'parent_id' => 2]], $after->get('children'));
        self::assertSame([['id' => 100, 'child_parent_id' => 2]], $after->get('grandchildren'));
    }

    public function testDeleteSetNullCarriesOriginalRowIntoNextLevel(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_id'],
                'parents',
                ['id'],
                ReferentialAction::SetNull,
            )],
        ));
        $registry->register('grandchildren', new TableDefinition(
            ['id', 'child_parent_id'],
            ['id' => 'INT', 'child_parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_child' => new ForeignKeyDefinition(
                ['child_parent_id'],
                'children',
                ['parent_id'],
                onUpdate: ReferentialAction::SetNull,
            )],
        ));
        $store = new ShadowStore();
        $store->set('parents', [['id' => 1]]);
        $store->set('children', [['id' => 10, 'parent_id' => 1]]);
        $store->set('grandchildren', [['id' => 100, 'child_parent_id' => 1]]);
        $before = $store->snapshot();
        $store->set('parents', []);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $store,
            new DeleteMutation('parents', ['id']),
            [['id' => 1]],
            'DELETE',
        );

        self::assertSame([['id' => 10, 'parent_id' => null]], $store->get('children'));
        self::assertSame([['id' => 100, 'child_parent_id' => null]], $store->get('grandchildren'));
    }

    public function testEmptySelfReferentialCascadeTerminatesWithoutSyntheticEvents(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('nodes', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_id'],
                'nodes',
                ['id'],
                ReferentialAction::Cascade,
            )],
        ));
        $before = new ShadowStore();
        $before->set('nodes', [['id' => 1, 'parent_id' => null], ['id' => 2, 'parent_id' => null]]);
        $after = $before->snapshot();
        $after->set('nodes', [['id' => 1, 'parent_id' => null]]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $after,
            new DeleteMutation('nodes', ['id']),
            [['id' => 2, 'parent_id' => null]],
            'DELETE',
        );

        self::assertSame([['id' => 1, 'parent_id' => null]], $after->get('nodes'));
    }

    public function testCascadeNormalizesSparseChildRowIndexes(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_id'],
            ['id' => 'INT', 'parent_id' => 'INT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_id'],
                'parents',
                ['id'],
                onUpdate: ReferentialAction::Cascade,
            )],
        ));
        $before = new ShadowStore();
        $before->set('parents', [['id' => 1]]);
        $before->set('children', [4 => ['id' => 10, 'parent_id' => 1]]);
        $after = $before->snapshot();
        $after->set('parents', [['id' => 2]]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $after,
            new UpdateMutation('parents', ['id']),
            [['id' => 2, '__ztd_original_id' => 1]],
            'UPDATE',
        );

        self::assertSame([0], array_keys($after->get('children')));
        self::assertSame([['id' => 10, 'parent_id' => 2]], $after->get('children'));
    }

    public function testRowsWithMissingPrimaryKeyCannotMatchRowsThatHaveTheKey(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(
            ['id', 'code'],
            ['id' => 'INT', 'code' => 'TEXT'],
            ['id'],
            ['id'],
            [],
        ));
        $before = new ShadowStore();
        $before->set('parents', [['code' => 'a']]);
        $after = new ShadowStore();
        $after->set('parents', [['id' => 1, 'code' => 'a']]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $after,
            new InsertMutation('unrelated'),
            [],
            'CHANGE',
        );

        self::assertSame([['id' => 1, 'code' => 'a']], $after->get('parents'));
    }

    public function testFallbackContinuesAfterIdentityMatchedRow(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('parents', new TableDefinition(
            ['id', 'code'],
            ['id' => 'INT', 'code' => 'TEXT'],
            ['id'],
            ['id', 'code'],
            [],
        ));
        $registry->register('children', new TableDefinition(
            ['id', 'parent_code'],
            ['id' => 'INT', 'parent_code' => 'TEXT'],
            ['id'],
            ['id'],
            [],
            foreignKeys: ['fk_parent' => new ForeignKeyDefinition(
                ['parent_code'],
                'parents',
                ['code'],
                onUpdate: ReferentialAction::Cascade,
            )],
        ));
        $before = new ShadowStore();
        $before->set('parents', [['id' => 1, 'code' => 'a'], ['id' => 2, 'code' => 'b']]);
        $before->set('children', [['id' => 10, 'parent_code' => 'a'], ['id' => 20, 'parent_code' => 'b']]);
        $after = $before->snapshot();
        $after->set('parents', [['id' => 1, 'code' => 'aa'], ['id' => 2, 'code' => 'bb']]);

        (new ReferentialIntegrityEnforcer($registry))->synchronize(
            $before,
            $after,
            new UpdateMutation('parents', ['id']),
            [['id' => 1, 'code' => 'aa', '__ztd_original_id' => 1]],
            'UPDATE',
        );

        self::assertSame([
            ['id' => 10, 'parent_code' => 'aa'],
            ['id' => 20, 'parent_code' => 'bb'],
        ], $after->get('children'));
    }
}
