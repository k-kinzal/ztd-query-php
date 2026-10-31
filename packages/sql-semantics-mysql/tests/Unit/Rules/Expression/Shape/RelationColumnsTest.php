<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\RelationColumns;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RelationColumns::class)]
#[Medium]
final class RelationColumnsTest extends TestCase
{
    public function testOfDistinguishesDeclaredCommonColumnsFromCatalogColumns(): void
    {
        $source = new M\Relation\TableReference(new QualifiedName(new Name('c')));
        self::assertNull((new RelationColumns())->of($source));
        self::assertEquals([new Name('a')], (new RelationColumns())->of($source, ['c' => [new Name('a')]]));
        self::assertNull((new RelationColumns())->of(new M\Relation\Dual()));
    }

    public function testQualifiedRespectsAnAliasAndItsAbsentDatabase(): void
    {
        $source = new M\Relation\DerivedTable(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name('a'))]), new Name('q'));
        self::assertEquals([new Name('a')], (new RelationColumns())->qualified($source, new QualifiedName(new Name('q'))));
        self::assertNull((new RelationColumns())->qualified($source, new QualifiedName(new Name('q'), new Name('d'))));
    }

    public function testCombinedDoesNotInventAWidthForAnUnknownMember(): void
    {
        self::assertNull((new RelationColumns())->combined([[new Name('a')], null]));
        self::assertEquals([new Name('a'), null], (new RelationColumns())->combined([[new Name('a')], [null]]));
    }

    public function testJoinedCoalescesCommonColumnsInThePreservedSidesOrder(): void
    {
        $left = new M\Relation\DerivedTable(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name('a'))]), new Name('q'));
        $right = new M\Relation\DerivedTable(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name('A')), new M\Query\SelectExpression(new M\Literal\NumberLiteral('2'), new Name('b'))]), new Name('r'));
        $join = new M\Relation\JoinedTable($left, M\Relation\Join\JoinOperator::NaturalRight, $right);
        self::assertEquals([new Name('A'), new Name('b')], (new RelationColumns())->joined($join));
    }
}
