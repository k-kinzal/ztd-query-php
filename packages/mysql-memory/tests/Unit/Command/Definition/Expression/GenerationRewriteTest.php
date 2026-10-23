<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Expression;

use MySqlMemory\Command\Definition\Expression\GenerationRewrite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(GenerationRewrite::class)]
#[Small]
final class GenerationRewriteTest extends TestCase
{
    /**
     * @return iterable<array{M\Table\Column\ColumnDefinition}>
     */
    public static function providerColumns(): iterable
    {
        $expression = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, new M\Variable\SystemVariable(new Name('version')), new M\Literal\NumberLiteral('1'));
        yield [new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\Generation\DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), $expression))];
    }

    public function testExpressionPreservesNestedArithmeticAroundAnOmittedOperand(): void
    {
        $inner = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Minus, new M\Variable\SystemVariable(new Name('version')), new M\Literal\NumberLiteral('2'));
        $outer = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Multiply, new M\Expression\Grouped($inner), new M\Literal\NumberLiteral('3'));
        $result = (new GenerationRewrite())->expression($outer);
        self::assertInstanceOf(M\Expression\Operator\Arithmetic::class, $result);
        self::assertInstanceOf(M\Expression\Grouped::class, $result->left);
        self::assertInstanceOf(M\Expression\Operator\Unary::class, $result->left->operand);
        self::assertSame(M\Expression\Operator\UnaryOperator::Minus, $result->left->operand->operator);
        self::assertInstanceOf(M\Variable\SystemVariable::class, $inner->left);
    }

    #[DataProvider('providerColumns')]
    public function testColumnKeepsTheSourceAndProducesAnOrdinaryDeclaration(M\Table\Column\ColumnDefinition $column): void
    {
        $result = (new GenerationRewrite())->column($column);
        self::assertInstanceOf(M\Table\Column\GeneratedColumn::class, $result->specification);
        self::assertSame($column->name, $result->name);
        self::assertInstanceOf(M\Table\Column\Generation\DeferredGeneratedColumn::class, $column->specification);
    }

    #[DataProvider('providerColumns')]
    public function testCreateKeepsEveryOtherCreationOperand(M\Table\Column\ColumnDefinition $column): void
    {
        $source = new M\Routine\Program\Write\DeferredCreateTable(new QualifiedName(new Name('t')), [$column], temporaryWords: 1, ifNotExists: true);
        $result = (new GenerationRewrite())->create($source);
        self::assertInstanceOf(M\Table\CreateTable::class, $result);
        self::assertSame([$source->name, 1, true], [$result->name, $result->temporaryWords, $result->ifNotExists]);
    }

    #[DataProvider('providerColumns')]
    public function testCommandsKeepsTheOrderedAlterActions(M\Table\Column\ColumnDefinition $column): void
    {
        $source = new M\Routine\Program\Write\DeferredAlterTable(new QualifiedName(new Name('t')), [new M\Alter\Column\AddColumn($column)]);
        $result = (new GenerationRewrite())->commands($source);
        self::assertCount(1, $result);
        self::assertInstanceOf(M\Alter\Column\AddColumn::class, $result[0]);
        self::assertInstanceOf(M\Table\Column\GeneratedColumn::class, $result[0]->column->specification);
    }

    public function testColumnRetainsAnUnresolvedSystemOperandForItsOwnCondition(): void
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\Generation\DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Variable\SystemVariable(new Name('version'))));
        self::assertSame($column, (new GenerationRewrite())->column($column));
    }

    public function testColumnCapturesTheCurrentBindingWithoutChangingTheTemplate(): void
    {
        $parameter = new M\Literal\Parameter();
        $expression = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, $parameter, new M\Literal\NumberLiteral('1'));
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\Generation\DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), $expression));
        $result = (new GenerationRewrite([spl_object_id($parameter) => new M\Literal\NumberLiteral('7')]))->column($column);
        self::assertInstanceOf(M\Table\Column\GeneratedColumn::class, $result->specification);
        self::assertInstanceOf(M\Expression\Operator\Arithmetic::class, $result->specification->expression);
        self::assertInstanceOf(M\Literal\NumberLiteral::class, $result->specification->expression->left);
        self::assertSame('7', $result->specification->expression->left->text);
        self::assertSame($parameter, $expression->left);
    }
}
