<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\TruncateMutation;
use ZtdQuery\Shadow\ShadowStore;

final class TruncateMutationTest extends TestCase
{
    public function testApplyClearsAllRows(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Carol'],
        ]);

        $mutation = new TruncateMutation('users');
        $mutation->apply($store, []);

        $this->assertSame([], $store->get('users'));
    }

    public function testTableNameReturnsTableName(): void
    {
        $mutation = new TruncateMutation('users');

        $this->assertSame('users', $mutation->tableName());
    }

    public function testApplyOnEmptyTableDoesNothing(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);

        $mutation = new TruncateMutation('users');
        $mutation->apply($store, []);

        $this->assertSame([], $store->get('users'));
    }

    public function testApplyIgnoresProvidedRows(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);

        $mutation = new TruncateMutation('users');
        // Rows parameter is ignored for TRUNCATE
        $mutation->apply($store, [['id' => 2]]);

        $this->assertSame([], $store->get('users'));
    }
}
