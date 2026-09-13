<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Schema\Partition\StorageTable::class)]
final class StorageTableTest extends TestCase
{
    public function testStorageTableFollowsNestedParentsAndStopsCycles(): void
    {
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $base = new \ZtdQuery\Schema\TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], [], []);
        $registry->register('parent', $base);
        $registry->register('child', $base->withPartitionRelation(new \ZtdQuery\Schema\TablePartitionRelation('parent', 'id < 10')));
        $registry->register('grandchild', $base->withPartitionRelation(new \ZtdQuery\Schema\TablePartitionRelation('child', 'id < 5')));
        $resolver = new \ZtdQuery\Platform\Postgres\Schema\Partition\StorageTable($registry);
        self::assertSame('parent', $resolver->storageTable('grandchild'));
        self::assertSame('unknown', $resolver->storageTable('unknown'));
        $registry->register('parent', $base->withPartitionRelation(new \ZtdQuery\Schema\TablePartitionRelation('child', 'id < 20')));
        self::assertSame('child', $resolver->storageTable('child'));
    }
}
