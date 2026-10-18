<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(LegacySubqueries::class)]
#[Small]
final class LegacySubqueriesTest extends TestCase
{
    public function testContainsDistinguishesACompoundUseFromABareSubquery(): void
    {
        $one = new M\Literal\NumberLiteral('1');
        $query = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression($one)]));
        $rule = new LegacySubqueries();
        self::assertFalse($rule->contains(new M\Expression\Grouped($query)));
        self::assertTrue($rule->contains(new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, new M\Literal\NumberLiteral('2'), $query)));
        self::assertTrue($rule->descendant($query));
        self::assertFalse($rule->descendant($one));
    }

    public function testDescendantDoesNotTreatAnInQueryAsAScalarPosition(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        self::assertFalse((new LegacySubqueries())->descendant(new M\Expression\Subquery\InQuery(new M\Literal\NumberLiteral('2'), $query)));
    }

    public function testColumnsOnlyFindsRetainedGenerationDeclarations(): void
    {
        $one = new M\Literal\NumberLiteral('1');
        $query = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression($one)]));
        $type = new M\Type\Integral(M\Type\Kind\IntegralKind::Int);
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('g')), new M\Table\Column\DeferredGeneratedColumn($type, new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, new M\Literal\NumberLiteral('2'), $query)));
        self::assertTrue((new LegacySubqueries())->columns([$column]));
        self::assertFalse((new LegacySubqueries())->columns([]));
    }
}
