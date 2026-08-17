<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Schema\ReferentialAction;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\ReferentialIntegrityEnforcer;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ReferentialIntegrityEnforcer::class)]
#[UsesClass(ForeignKeyDefinition::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(DeleteMutation::class)]
#[UsesClass(InsertMutation::class)]
#[UsesClass(UpdateMutation::class)]
#[UsesClass(MutationRowIdentity::class)]
#[UsesClass(ShadowStore::class)]
final class ReferentialIntegrityEnforcerTest extends TestCase
{
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
}
