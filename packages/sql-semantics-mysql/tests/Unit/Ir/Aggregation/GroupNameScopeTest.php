<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Aggregation\Aggregate;
use SqlSemantics\Platform\MySql\Ir\Aggregation\AggregateOperator;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupColumn;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupNameReference;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupNameScope;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(GroupNameScope::class)]
#[Small]
final class GroupNameScopeTest extends TestCase
{
    public function testCollidesOnlyForACompetingValue(): void
    {
        $column = new ColumnReference(0);
        $name = new Identifier('ID');
        $label = new Identifier('id');
        $scope = new GroupNameScope();
        self::assertFalse($scope->collides($column, $name, [new GroupColumn(new ColumnReference(0), $label)]));
        self::assertTrue($scope->collides($column, $name, [new GroupColumn(new Aggregate(AggregateOperator::Count), $label)]));
    }

    public function testOwnedReturnsThePhysicalKey(): void
    {
        $column = new ColumnReference(0);
        $name = new Identifier('id');
        $source = new TableSource(new Identifier('app'), new Identifier('events'), [$name]);
        $results = [new GroupColumn(new Aggregate(AggregateOperator::Count), $name)];
        $bare = new GroupNameReference($column, new Identifier('ID'));

        self::assertSame($column, (new GroupNameScope())->owned($bare, $source, $results));
    }

    public function testMembersFindsTheSamePhysicalKeyForBothUses(): void
    {
        $column = new ColumnReference(0);
        $name = new Identifier('id');
        $source = new TableSource(new Identifier('app'), new Identifier('events'), [$name]);
        $results = [new GroupColumn(new Aggregate(AggregateOperator::Count), $name)];
        $bare = new GroupNameReference($column, new Identifier('ID'));

        self::assertSame([0 => true], (new GroupNameScope())->members([$column, $bare], $source, $results));
    }
}
