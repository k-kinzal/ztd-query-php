<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\QueryColumns;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(QueryColumns::class)]
#[Medium]
final class QueryColumnsTest extends TestCase
{
    public function testOfRetainsThePositionsOfAnExistsProjectionWithoutExpandingItsStar(): void
    {
        self::assertSame([null], (new QueryColumns())->of(new M\Query\Select([], [new M\Query\Star()]), true));
    }

    public function testCommonBindsEarlierDefinitionsBeforeLaterReferences(): void
    {
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [new Name('a')], new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))])), new M\Query\With\CommonTableExpression(new Name('d'), [], new M\Query\ExplicitTable(new QualifiedName(new Name('c'))))]);
        self::assertEquals(['c' => [new Name('a')], 'd' => [new Name('a')]], (new QueryColumns())->common($with));
        self::assertSame(['outer' => null], (new QueryColumns())->common(null, ['outer' => null]));
    }

    public function testSelectLeavesACatalogWildcardOpen(): void
    {
        $query = new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('t'))));
        self::assertNull((new QueryColumns())->select($query));
        self::assertEquals([new Name('a')], (new QueryColumns())->select($query, ['t' => [new Name('a')]]));
    }

    public function testNameKeepsAliasesAndLeavesClientDependentNamesUnresolved(): void
    {
        $columns = new QueryColumns();
        self::assertSame('label', $columns->name(new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name(' label')))?->value);
        self::assertNull($columns->name(new M\Query\SelectExpression(new M\Literal\StringLiteral(['é']))));
        self::assertSame('1', $columns->name(new M\Query\SelectExpression(new M\Literal\NumberLiteral('1')))?->value);
    }
}
