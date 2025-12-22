<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\Mutation\DropTableMutation;
use ZtdQuery\Shadow\ShadowStore;

final class DropTableMutationTest extends TestCase
{
    public function testApplyUnregistersTableFromSchema(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY)');
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);

        $mutation = new DropTableMutation('users', $registry);
        $mutation->apply($store, []);

        $this->assertNull($registry->get('users'));
    }

    public function testApplyClearsDataFromShadowStore(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY)');
        $store = new ShadowStore();
        $store->set('users', [['id' => 1], ['id' => 2]]);

        $mutation = new DropTableMutation('users', $registry);
        $mutation->apply($store, []);

        $this->assertSame([], $store->get('users'));
    }

    public function testTableNameReturnsTableName(): void
    {
        $registry = new SchemaRegistry();
        $mutation = new DropTableMutation('users', $registry);

        $this->assertSame('users', $mutation->tableName());
    }

    public function testApplyThrowsExceptionWhenTableDoesNotExist(): void
    {
        $registry = new SchemaRegistry();
        $store = new ShadowStore();

        $mutation = new DropTableMutation('users', $registry);

        $this->expectException(SchemaNotFoundException::class);
        $this->expectExceptionMessage("Table 'users' does not exist.");

        $mutation->apply($store, []);
    }

    public function testApplyWithIfExistsSkipsWhenTableDoesNotExist(): void
    {
        $registry = new SchemaRegistry();
        $store = new ShadowStore();

        $mutation = new DropTableMutation('users', $registry, true);

        // Should not throw
        $mutation->apply($store, []);

        $this->assertNull($registry->get('users'));
    }

    public function testApplyWithIfExistsDropsExistingTable(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT)');
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);

        $mutation = new DropTableMutation('users', $registry, true);
        $mutation->apply($store, []);

        $this->assertNull($registry->get('users'));
        $this->assertSame([], $store->get('users'));
    }
}
