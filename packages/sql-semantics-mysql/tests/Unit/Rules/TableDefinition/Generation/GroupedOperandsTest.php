<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\GroupedOperands;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

#[CoversClass(GroupedOperands::class)]
#[Small]
final class GroupedOperandsTest extends TestCase
{
    /**
     * @return iterable<string, array{Scalar}>
     */
    public static function providerGrouped(): iterable
    {
        yield 'aggregate' => [new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [new M\Literal\NumberLiteral('1')])];
        yield 'window' => [new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new M\Call\Window\WindowSpec())];
        yield 'nested' => [new M\Call\FunctionCall(new Name('ABS'), [new M\Call\CallArgument(new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [new M\Literal\NumberLiteral('1')]))])];
    }

    #[DataProvider('providerGrouped')]
    public function testContainsExcludesGroupingFromAnOrdinaryGenerationExpression(Scalar $expression): void
    {
        self::assertTrue((new GroupedOperands())->contains($expression));
        $type = new M\Type\Integral(M\Type\Kind\IntegralKind::Int);
        $column = new M\Table\Column\Generation\DeferredGeneratedColumn($type, $expression);
        self::assertSame($expression, $column->expression);
        self::assertFalse((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms())->requires($column));
        $this->expectExceptionMessage('An ordinary generation expression has no aggregate or window role.');
        new M\Table\Column\GeneratedColumn($type, $expression);
    }

    public function testDeferredLeavesSubqueryRefusalInItsOwnPhase(): void
    {
        $expression = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [new M\Literal\NumberLiteral('1')]))]));
        self::assertTrue((new GroupedOperands())->contains($expression));
        self::assertFalse((new GroupedOperands())->deferred($expression));
        self::assertFalse((new GroupedOperands())->contains(new M\Literal\NumberLiteral('1')));
    }
}
