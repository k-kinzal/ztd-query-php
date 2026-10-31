<?php

declare(strict_types=1);

namespace Tests\Unit\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Resolution\ProjectionScope;

#[CoversClass(ProjectionScope::class)]
#[Medium]
final class ProjectionScopeTest extends TestCase
{
    public function testBindExposesOnlyCompletedItems(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS x, 2 AS y');
        $first = $operation->field('x');
        $second = $operation->field('y');
        self::assertNotNull($first->name);
        self::assertNotNull($first->expression);
        self::assertNotNull($second->name);
        self::assertNotNull($second->expression);
        $scope = new ProjectionScope([0 => [$first->name, $first->expression], 2 => [$second->name, $second->expression]]);
        self::assertNull($scope->field(0));
        $scope->bind(0, $first);

        self::assertSame($first, $scope->field(0));
        self::assertNull($scope->field(2));
        self::assertSame($second->expression, $scope->items[2][1]);
    }

    public function testFieldKeepsTheDeclarationPositionAfterAStar(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS x');
        $field = $operation->field('x');
        self::assertNotNull($field->name);
        self::assertNotNull($field->expression);
        $scope = new ProjectionScope([3 => [$field->name, $field->expression]]);
        $scope->bind(3, $field);

        self::assertNull($scope->field(0));
        self::assertSame($field, $scope->field(3));
    }
}
