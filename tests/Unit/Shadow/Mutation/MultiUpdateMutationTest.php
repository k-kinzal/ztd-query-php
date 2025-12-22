<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\MultiUpdateMutation;
use ZtdQuery\Shadow\ShadowStore;

final class MultiUpdateMutationTest extends TestCase
{
    public function testApplyUpdatesMultipleTables(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice', 'status' => 'active'],
            ['id' => 2, 'name' => 'Bob', 'status' => 'active'],
        ]);
        $store->set('profiles', [
            ['user_id' => 1, 'verified' => false],
            ['user_id' => 2, 'verified' => false],
        ]);

        $mutation = new MultiUpdateMutation([
            'users' => ['id'],
            'profiles' => ['user_id'],
        ]);

        $mutation->apply($store, [
            ['id' => 1, 'name' => 'Alice Updated', 'status' => 'inactive', 'user_id' => 1, 'verified' => true],
        ]);

        $this->assertSame('Alice Updated', $store->get('users')[0]['name']);
        $this->assertSame('inactive', $store->get('users')[0]['status']);
        $this->assertTrue($store->get('profiles')[0]['verified']);
    }

    public function testTableNameReturnsPrimaryTable(): void
    {
        $mutation = new MultiUpdateMutation([
            'users' => ['id'],
            'profiles' => ['user_id'],
        ]);

        $this->assertSame('users', $mutation->tableName());
    }

    public function testTableNamesReturnsAllTableNames(): void
    {
        $mutation = new MultiUpdateMutation([
            'users' => ['id'],
            'profiles' => ['user_id'],
            'settings' => ['user_id'],
        ]);

        $this->assertSame(['users', 'profiles', 'settings'], $mutation->tableNames());
    }

    public function testApplyWithEmptyTablesReturnsEmptyPrimaryTable(): void
    {
        $mutation = new MultiUpdateMutation([]);

        $this->assertSame('', $mutation->tableName());
    }

    public function testApplyUpdatesMultipleRows(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Carol'],
        ]);

        $mutation = new MultiUpdateMutation([
            'users' => ['id'],
        ]);

        $mutation->apply($store, [
            ['id' => 1, 'name' => 'Alice Updated'],
            ['id' => 3, 'name' => 'Carol Updated'],
        ]);

        $this->assertSame('Alice Updated', $store->get('users')[0]['name']);
        $this->assertSame('Bob', $store->get('users')[1]['name']);
        $this->assertSame('Carol Updated', $store->get('users')[2]['name']);
    }
}
