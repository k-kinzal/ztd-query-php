<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\TruncateMutation;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(ShadowStore::class)]
#[CoversClass(TruncateMutation::class)]
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

        self::assertSame([], $store->get('users'));
    }

    public function testTableNameReturnsTableName(): void
    {
        $mutation = new TruncateMutation('users');

        self::assertSame('users', $mutation->tableName());
    }

    public function testApplyOnEmptyTableDoesNothing(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);

        $mutation = new TruncateMutation('users');
        $mutation->apply($store, []);

        self::assertSame([], $store->get('users'));
    }

    public function testApplyIgnoresProvidedRows(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);

        $mutation = new TruncateMutation('users');
        $mutation->apply($store, [['id' => 2]]);

        self::assertSame([], $store->get('users'));
    }
}
