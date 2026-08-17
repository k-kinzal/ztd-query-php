<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\MultiTruncateMutation;
use ZtdQuery\Shadow\ShadowStore;

#[UsesClass(ShadowStore::class)]
#[CoversClass(MultiTruncateMutation::class)]
final class MultiTruncateMutationTest extends TestCase
{
    public function testApplyClearsEveryTargetTable(): void
    {
        $store = new ShadowStore();
        $store->set('alpha', [['id' => 1]]);
        $store->set('beta', [['id' => 2]]);
        $store->set('untouched', [['id' => 3]]);

        $mutation = new MultiTruncateMutation(['alpha', 'beta']);
        $mutation->apply($store, [['id' => 99]]);

        self::assertSame([], $store->get('alpha'));
        self::assertSame([], $store->get('beta'));
        self::assertSame([['id' => 3]], $store->get('untouched'));
    }

    public function testTableAccessorsPreserveStatementOrder(): void
    {
        $mutation = new MultiTruncateMutation(['alpha', 'beta']);

        self::assertSame('alpha', $mutation->tableName());
        self::assertSame(['alpha', 'beta'], $mutation->tableNames());
    }

    public function testEmptyTargetsHaveEmptyPrimaryTable(): void
    {
        $mutation = new MultiTruncateMutation([]);

        self::assertSame('', $mutation->tableName());
        self::assertSame([], $mutation->tableNames());
    }
}
