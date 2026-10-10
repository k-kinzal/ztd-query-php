<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(Scope::class)]
#[Small]
final class ScopeTest extends TestCase
{
    public function testPlacePutsEachRelationAtTheEndOfTheRow(): void
    {
        $scope = new Scope();
        $first = new NumberLiteral('1');
        $second = new NumberLiteral('2');

        self::assertSame([0, 2], [$scope->place($first, [Domain::integer(), Domain::integer()], ['a', 'b']), $scope->place($second, [Domain::double()], ['c'])]);
        self::assertSame([spl_object_id($first) => ['a', 'b'], spl_object_id($second) => ['c']], $scope->names);
    }

    public function testWidthCountsTheColumnsOfEveryPlacedRelation(): void
    {
        $scope = new Scope();
        $scope->place(new NumberLiteral('1'), [Domain::integer(), Domain::integer()]);
        $scope->place(new NumberLiteral('2'), [Domain::integer(), Domain::integer(), Domain::integer()]);

        self::assertSame(5, $scope->width());
    }

    public function testWidthIsZeroForAnEmptyScope(): void
    {
        self::assertSame(0, (new Scope())->width());
    }

    public function testLocateFindsARelationOfAnEnclosingBlockWithItsDepth(): void
    {
        $relation = new NumberLiteral('1');
        $outer = new Scope();
        $outer->place($relation, [Domain::integer()]);
        $inner = new Scope(new Scope($outer));

        self::assertSame([2, $outer], $inner->locate($relation));
    }

    public function testLocateAnswersNullForARelationNoBlockPlaces(): void
    {
        self::assertNull((new Scope(new Scope()))->locate(new NumberLiteral('1')));
    }

    public function testBindBindsANodeToAnExpression(): void
    {
        $scope = new Scope();
        $node = new NumberLiteral('1');
        $evaluable = new Constant(Domain::integer(), 1);
        $scope->bind($node, $evaluable);

        self::assertSame([0, $evaluable], $scope->bound($node));
    }

    public function testBoundFindsTheExpressionOfAnEnclosingBlockWithItsDepth(): void
    {
        $outer = new Scope();
        $node = new NumberLiteral('1');
        $evaluable = new Constant(Domain::integer(), 1);
        $outer->bind($node, $evaluable);

        self::assertSame([1, $evaluable], (new Scope($outer))->bound($node));
    }

    public function testBoundAnswersNullForANodeNoBlockBinds(): void
    {
        self::assertNull((new Scope())->bound(new NumberLiteral('1')));
    }

    public function testGroupedKeepsThePlacementButNotTheBoundExpressions(): void
    {
        $outer = new Scope();
        $scope = new Scope($outer);
        $relation = new NumberLiteral('1');
        $node = new NumberLiteral('2');
        $scope->place($relation, [Domain::integer(), Domain::double()], ['a', 'b']);
        $scope->bind($node, new Constant(Domain::integer(), 2));
        $scope->derived[spl_object_id($relation)] = 'x';
        $grouped = $scope->grouped();

        self::assertSame([$outer, 2, [0, $grouped], null, ['a', 'b'], 'x'], [$grouped->outer, $grouped->width(), $grouped->locate($relation), $grouped->bound($node), $grouped->names[spl_object_id($relation)], $grouped->derived[spl_object_id($relation)]]);
    }
}
