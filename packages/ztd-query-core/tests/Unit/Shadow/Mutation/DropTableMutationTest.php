<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\DropTableMutation;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

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

        $mutation = new DropTableMutation('users', $registry);
        $mutation->apply($store, []);

        self::assertNull($registry->get('users'));
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

        $mutation = new DropTableMutation('users', $registry);
        $mutation->apply($store, []);

        self::assertSame([], $store->get('users'));
    }

    public function testTableNameReturnsTableName(): void
    {
        $registry = new TableDefinitionRegistry();
        $mutation = new DropTableMutation('users', $registry);

        self::assertSame('users', $mutation->tableName());
    }

    public function testApplyThrowsExceptionWhenTableDoesNotExist(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $mutation = new DropTableMutation('users', $registry);

        $this->expectException(SchemaNotFoundException::class);
        $this->expectExceptionMessage("Table 'users' does not exist.");

        $mutation->apply($store, []);
    }

    public function testApplyWithIfExistsSkipsWhenTableDoesNotExist(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $mutation = new DropTableMutation('users', $registry, true);

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

        $mutation = new DropTableMutation('users', $registry, true);
        $mutation->apply($store, []);

        self::assertNull($registry->get('users'));
        self::assertSame([], $store->get('users'));
    }
}
