<?php

declare(strict_types=1);

namespace Tests\Unit\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Resolution\AggregationScope;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Shape\RowShape;

#[CoversClass(AggregationScope::class)]
#[Small]
final class AggregationScopeTest extends TestCase
{
    public function testRegisterRetainsDistinctOccurrencesAndRegistersEachOnlyOnce(): void
    {
        $scope = new AggregationScope([]);
        $first = new IntegerLiteral('1');
        $second = new IntegerLiteral('1');
        $scope->register($first);
        $scope->register($second);
        $scope->register($first);

        self::assertSame([$first, $second], $scope->expressions());
    }

    public function testExpressionsReturnsACopyOfTheCollectedList(): void
    {
        $scope = new AggregationScope([]);
        $before = $scope->expressions();
        $expression = new IntegerLiteral('1');
        $scope->register($expression);

        self::assertSame([], $before);
        self::assertSame([$expression], $scope->expressions());
    }

    public function testContainsDistinguishesOccurrencesWithTheSameTableName(): void
    {
        $first = new TableInput(new QualifiedName(new Name('t')));
        $second = new TableInput(new QualifiedName(new Name('t')));
        $scope = new AggregationScope([new VisibleRelation($first, new RowShape([]))]);

        self::assertTrue($scope->contains($first));
        self::assertFalse($scope->contains($second));
    }
}
