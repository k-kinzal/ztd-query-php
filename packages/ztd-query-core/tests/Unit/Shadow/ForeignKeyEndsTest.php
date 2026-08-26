<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ForeignKeyEnds;

#[CoversClass(ForeignKeyEnds::class)]
#[UsesClass(ForeignKeyDefinition::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
final class ForeignKeyEndsTest extends TestCase
{
    public function testReferencedColumnsAnswersTheColumnsTheKeyNames(): void
    {
        $ends = new ForeignKeyEnds(new TableDefinitionRegistry());
        $foreignKey = new ForeignKeyDefinition(['order_id'], 'order', ['id']);

        self::assertSame(['id'], $ends->referencedColumns($foreignKey));
    }

    public function testReferencedColumnsFallsBackToTheParentsPrimaryKey(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('order', new TableDefinition(['id', 'total'], [], ['id'], [], []));
        $ends = new ForeignKeyEnds($registry);

        self::assertSame(['id'], $ends->referencedColumns(new ForeignKeyDefinition(['order_id'], 'order', [])));
    }

    public function testReferencedColumnsIsEmptyWhereTheParentIsUnknown(): void
    {
        $ends = new ForeignKeyEnds(new TableDefinitionRegistry());

        self::assertSame([], $ends->referencedColumns(new ForeignKeyDefinition(['order_id'], 'order', [])));
    }

    public function testAreBalancedWhenBothEndsNameTheSameNumberOfColumns(): void
    {
        $ends = new ForeignKeyEnds(new TableDefinitionRegistry());

        self::assertTrue($ends->areBalanced(new ForeignKeyDefinition(['order_id'], 'order', ['id'])));
    }

    public function testAreBalancedIsFalseWhereTheEndsDisagree(): void
    {
        $ends = new ForeignKeyEnds(new TableDefinitionRegistry());

        self::assertFalse($ends->areBalanced(new ForeignKeyDefinition(['shop_id', 'no'], 'order', ['id'])));
    }
}
