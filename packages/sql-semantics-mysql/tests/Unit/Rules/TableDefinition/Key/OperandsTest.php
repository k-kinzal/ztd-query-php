<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Key\Operands;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

#[CoversClass(Operands::class)]
#[Small]
final class OperandsTest extends TestCase
{
    /**
     * @return iterable<string, array{Scalar}>
     */
    public static function providerDeferred(): iterable
    {
        yield 'parameter' => [new M\Literal\Parameter()];
        yield 'variable' => [new M\Variable\UserVariable(new Name('x'))];
        yield 'default' => [new M\Expression\Access\DefaultOfColumn(new M\Name\ColumnUse(new Name('a')))];
        yield 'aggregate' => [new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [new M\Literal\NumberLiteral('1')])];
        yield 'window' => [new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new M\Call\Window\WindowSpec())];
        yield 'stored call' => [new M\Call\FunctionCall(new Name('f'))];
        yield 'random' => [new M\Call\FunctionCall(new Name('RAND'))];
        yield 'query' => [new M\Expression\Subquery\InQuery(new M\Literal\NumberLiteral('1'), new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]))];
        yield 'nested parameter' => [new M\Call\FunctionCall(new Name('ABS'), [new M\Call\CallArgument(new M\Literal\Parameter())])];
    }

    #[DataProvider('providerDeferred')]
    public function testOrdinaryKeysCannotAcceptDeferredExpressionRoles(Scalar $expression): void
    {
        self::assertFalse((new Operands())->ordinary($expression));
        self::assertSame($expression, (new M\Table\Key\Deferred\ExpressionPart($expression))->expression);
        $this->expectExceptionMessage('An ordinary functional key has an index-expression role.');
        new M\Table\Key\ExpressionPart($expression);
    }

    public function testNativeDeterministicCallsPreserveTheirOpenColumnDependency(): void
    {
        $expression = new M\Call\FunctionCall(new Name('ABS'), [new M\Call\CallArgument(new M\Name\ColumnUse(new Name('a')))]);
        self::assertTrue((new Operands())->ordinary($expression));
        self::assertSame($expression, (new M\Table\Key\ExpressionPart($expression))->expression);
    }

    public function testContainsFindsAForbiddenOperandInsideANativeCall(): void
    {
        $expression = new M\Call\FunctionCall(new Name('ABS'), [new M\Call\CallArgument(new M\Literal\Parameter())]);
        self::assertTrue((new Operands())->contains($expression));
        self::assertFalse((new Operands())->forbidden($expression));
    }

    public function testForbiddenKeepsGroupRolesDistinctFromForbiddenCalls(): void
    {
        $aggregate = new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [new M\Literal\NumberLiteral('1')]);
        self::assertFalse((new Operands())->forbidden($aggregate));
        self::assertTrue((new Operands())->contains($aggregate));
        self::assertTrue((new Operands())->forbidden(new M\Literal\Parameter()));
    }

    public function testAConstantHasAnOrdinaryIndexExpressionRole(): void
    {
        self::assertTrue((new Operands())->ordinary(new M\Literal\NumberLiteral('1')));
    }
}
