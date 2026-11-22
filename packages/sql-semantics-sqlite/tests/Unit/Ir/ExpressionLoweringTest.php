<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ArithmeticOperator;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastTarget;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryKind;
use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternKind;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternMatch;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InList;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(ExpressionLowering::class)]
#[Small]
final class ExpressionLoweringTest extends TestCase
{
    public function testLowerPreservesArithmeticOperation(): void
    {
        $operation = (new Inspection())->inspect('SELECT (2 - 3)');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $result = $select->columns[0];
        self::assertInstanceOf(ResultColumn::class, $result);

        $value = (new ExpressionLowering())->lower($result->expression, new SourceBinding($operation->binding(), $select->from));
        self::assertInstanceOf(Arithmetic::class, $value);
        self::assertSame(ArithmeticOperator::Subtract, $value->operator);
    }

    public function testChoicePreservesTheNumberedBaseAndSelectedResult(): void
    {
        $operation = (new Inspection())->inspect('SELECT CASE ?02 WHEN 3 THEN 7 ELSE 8 END');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $result = $select->columns[0];
        self::assertInstanceOf(ResultColumn::class, $result);

        $value = (new ExpressionLowering())->lower($result->expression, new SourceBinding($operation->binding(), $select->from));
        self::assertInstanceOf(CaseExpression::class, $value);
        self::assertInstanceOf(Parameter::class, $value->base);
        self::assertSame('02', $value->base->label);
        self::assertCount(1, $value->branches);
    }

    public function testCastResolvesTypeNamesToTheirEffectiveAffinity(): void
    {
        $operation = (new Inspection())->inspect("SELECT CAST('7.0' AS FLOATING POINT)");
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $column = $select->columns[0];
        self::assertInstanceOf(ResultColumn::class, $column);
        $cast = (new ExpressionLowering())->lower($column->expression, new SourceBinding($operation->binding(), $select->from));

        self::assertInstanceOf(CastExpression::class, $cast);
        self::assertSame(CastTarget::Integer, $cast->target);
    }

    public function testPrefixKeepsLogicalNegationAroundTheNumberedSlot(): void
    {
        $operation = (new Inspection())->inspect('SELECT NOT ?02');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $column = $select->columns[0];
        self::assertInstanceOf(ResultColumn::class, $column);
        self::assertInstanceOf(Unary::class, $column->expression);
        $value = (new ExpressionLowering())->prefix($column->expression, new SourceBinding($operation->binding(), $select->from));

        self::assertInstanceOf(UnaryExpression::class, $value);
        self::assertSame(UnaryKind::Not, $value->kind);
        self::assertInstanceOf(Parameter::class, $value->operand);
        self::assertSame(2, $value->operand->slot);
    }

    public function testBinaryRecognizesTheGroupedTruthTestOperand(): void
    {
        $operation = (new Inspection())->inspect('SELECT 7 IS NOT (FALSE)');
        self::assertInstanceOf(Select::class, $operation->statement);
        $column = $operation->statement->columns[0];
        self::assertInstanceOf(ResultColumn::class, $column);
        self::assertInstanceOf(Binary::class, $column->expression);
        $value = (new ExpressionLowering())->binary($column->expression, new SourceBinding($operation->binding(), null));

        self::assertInstanceOf(TruthTest::class, $value);
        self::assertFalse($value->desired);
        self::assertTrue($value->negated);
    }

    public function testTruthWordProducesAnIntegerInAnOrdinaryScalarRole(): void
    {
        $operation = (new Inspection())->inspect('SELECT TRUE');
        self::assertInstanceOf(Select::class, $operation->statement);
        $column = $operation->statement->columns[0];
        self::assertInstanceOf(ResultColumn::class, $column);
        self::assertInstanceOf(TruthWord::class, $column->expression);
        $value = (new ExpressionLowering())->truthWord($column->expression, new SourceBinding($operation->binding(), null));

        self::assertInstanceOf(IntegerLiteral::class, $value);
        self::assertSame('1', $value->decimal);
    }

    public function testMembershipRetainsTheNegationAndWrittenCandidates(): void
    {
        $operation = (new Inspection())->inspect('SELECT ?02 NOT IN (?3, ?1)');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $column = $select->columns[0];
        self::assertInstanceOf(ResultColumn::class, $column);
        self::assertInstanceOf(InList::class, $column->expression);

        $test = (new ExpressionLowering())->membership($column->expression, new SourceBinding($operation->binding(), $select->from));

        self::assertInstanceOf(MembershipTest::class, $test);
        self::assertTrue($test->negated);
        self::assertInstanceOf(Parameter::class, $test->operand);
        self::assertSame('02', $test->operand->label);
        self::assertInstanceOf(Parameter::class, $test->items[0]->expression);
        self::assertSame('3', $test->items[0]->expression->label);
    }

    public function testCollatedResolvesTheBuiltinIdentityAndKeepsItsOperand(): void
    {
        $result = (new Semantics())->analyze('SELECT ?02 COLLATE nOcAsE AS value');
        self::assertInstanceOf(\SqlSemantics\Platform\Sqlite\Ir\Select::class, $result);
        $value = $result->columns[0]->expression;

        self::assertInstanceOf(CollatedExpression::class, $value);
        self::assertSame(Collation::NoCase, $value->collation);
        self::assertInstanceOf(Parameter::class, $value->operand);
        self::assertSame('02', $value->operand->label);
    }

    public function testPatternSelectsNativeLikeAndRetainsTheEscape(): void
    {
        $operation = (new Inspection())->inspect('SELECT ?02 NOT LIKE ?3 ESCAPE ?7');
        self::assertInstanceOf(Select::class, $operation->statement);
        $column = $operation->statement->columns[0];
        self::assertInstanceOf(ResultColumn::class, $column);
        self::assertInstanceOf(PatternMatch::class, $column->expression);
        $value = (new ExpressionLowering())->pattern($column->expression, new SourceBinding($operation->binding(), null));

        self::assertSame(PatternKind::AsciiLike, $value->kind);
        self::assertTrue($value->negated);
        self::assertInstanceOf(Parameter::class, $value->escape);
        self::assertSame('7', $value->escape->label);
    }

    public function testNullTestUsesNullSafeComparison(): void
    {
        $result = (new Semantics())->analyze('SELECT ?02 ISNULL AS value');
        self::assertInstanceOf(\SqlSemantics\Platform\Sqlite\Ir\Select::class, $result);
        $value = $result->columns[0]->expression;

        self::assertInstanceOf(Comparison::class, $value);
        self::assertSame(ComparisonOperator::Is, $value->operator);
    }

    public function testConditionalConvertsAnOrdinaryBuiltinToAnOrderedRole(): void
    {
        $inspected = (new Inspection())->inspect('SELECT COALESCE(NULL,7) AS n');
        self::assertInstanceOf(Select::class, $inspected->statement);
        $field = $inspected->statement->columns[0];
        self::assertInstanceOf(ResultColumn::class, $field);
        self::assertInstanceOf(FunctionCall::class, $field->expression);
        $binding = $inspected->binding();
        $operation = (new ExpressionLowering())->conditional($field->expression, new SourceBinding($binding, null));

        self::assertInstanceOf(Coalesce::class, $operation);
        self::assertCount(2, $operation->arguments);
    }
}
