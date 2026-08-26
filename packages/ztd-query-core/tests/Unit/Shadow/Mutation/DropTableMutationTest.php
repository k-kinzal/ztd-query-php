<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\DropTableMutation;
use ZtdQuery\Shadow\ShadowStore;

#[UsesClass(SchemaNotFoundException::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(ShadowStore::class)]
#[CoversClass(DropTableMutation::class)]
final class DropTableMutationTest extends TestCase
{
    public function testApplyUnregistersTableFromSchema(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            ['id'],
            ['id'],
            [],
        );
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);

        $mutation = new DropTableMutation('users', $registry, 'fixture statement');
        $mutation->apply($store, []);

        self::assertNull($registry->get('users'));
        self::assertTrue($registry->isRemoved('users'));
        self::assertSame(['users' => $definition], $registry->getAllRemoved());
    }

    public function testApplyClearsDataFromShadowStore(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            ['id'],
            ['id'],
            [],
        );
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1], ['id' => 2]]);

        $mutation = new DropTableMutation('users', $registry, 'fixture statement');
        $mutation->apply($store, []);

        self::assertSame([], $store->get('users'));
    }

    public function testTableNameReturnsTableName(): void
    {
        $registry = new TableDefinitionRegistry();
        $mutation = new DropTableMutation('users', $registry, 'fixture statement');

        self::assertSame('users', $mutation->tableName());
    }

    public function testApplyThrowsExceptionWhenTableDoesNotExist(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $mutation = new DropTableMutation('users', $registry, 'fixture statement');

        try {
            $mutation->apply($store, []);
            self::fail('Expected an unknown virtual table to fail.');
        } catch (SchemaNotFoundException $exception) {
            self::assertSame("Table 'users' does not exist.", $exception->getMessage());
            self::assertSame('fixture statement', $exception->getSql());
        }
    }

    public function testApplyWithIfExistsSkipsWhenTableDoesNotExist(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $mutation = new DropTableMutation('users', $registry, 'fixture statement', true);

        $mutation->apply($store, []);

        self::assertNull($registry->get('users'));
    }

    public function testApplyWithIfExistsDropsExistingTable(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            ['id'],
            ['id'],
            [],
        );
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);

        $mutation = new DropTableMutation('users', $registry, 'fixture statement', true);
        $mutation->apply($store, []);

        self::assertNull($registry->get('users'));
        self::assertSame([], $store->get('users'));
    }
}
