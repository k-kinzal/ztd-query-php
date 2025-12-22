<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\ShadowApplier;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\TestCase;

final class ShadowApplierTest extends TestCase
{
    public function testInsertUpdateDeleteMutations(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $applier = new ShadowApplier($store);
        $applier->apply(new InsertMutation('users'), [
            ['id' => 3, 'name' => 'Carol'],
        ]);
        $this->assertCount(3, $store->get('users'));

        $applier->apply(new UpdateMutation('users', ['id']), [
            ['id' => 1, 'name' => 'Alice Updated'],
        ]);
        $rows = $store->get('users');
        $this->assertSame('Alice Updated', $rows[0]['name']);

        $applier->apply(new DeleteMutation('users', ['id']), [
            ['id' => 2],
        ]);
        $ids = array_column($store->get('users'), 'id');
        $this->assertSame([1, 3], $ids);
    }
}
