<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Expression\Truth;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Precedence::class)]
#[Small]
final class PrecedenceTest extends TestCase
{
    public function testEdgesAnswerTheLevelsAndOperandsOfABinaryOperator(): void
    {
        $sum = new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2'));

        self::assertSame([Precedence::ADDITIVE, Precedence::ADDITIVE, $sum->left, $sum->right], (new Precedence())->edges($sum));
        self::assertNull((new Precedence())->edges(new NumberLiteral('1')));
    }

    public function testPostfixAnswersTheEdgesOfAFormWrittenAfterItsOperand(): void
    {
        $in = new InList(new NumberLiteral('1'), [new NumberLiteral('2')]);

        self::assertSame([Precedence::PREDICATE, Precedence::PREDICATE, $in->operand, null], (new Precedence())->postfix($in));
        self::assertNull((new Precedence())->postfix(new Not(new NumberLiteral('1'))));
    }

    public function testOpeningIsTheWeakestLevelOnTheLeftEdge(): void
    {
        $precedence = new Precedence();

        self::assertSame(Precedence::PREDICATE, $precedence->opening(new InList(new Arithmetic(ArithmeticOperator::Minus, new NumberLiteral('1'), new NumberLiteral('2')), [new NumberLiteral('3')])));
        self::assertSame(Precedence::PRIMARY, $precedence->opening(new Unary(UnaryOperator::Minus, new NumberLiteral('1'))));
        self::assertSame(Precedence::CLOSED, $precedence->opening(new NumberLiteral('1')));
    }

    public function testAdmitsAcceptsTheNonterminalsOfASlot(): void
    {
        $precedence = new Precedence();
        $comparison = new Comparison(ComparisonOperator::Equal, new NumberLiteral('1'), new NumberLiteral('2'));

        self::assertSame([true, false, true], [$precedence->admits($comparison, Precedence::BOOL_PRI), $precedence->admits($comparison, Precedence::BIT_EXPR), $precedence->admits(new Unary(UnaryOperator::Not, new NumberLiteral('3')), Precedence::SIMPLE_EXPR)]);
    }

    public function testTopIsTheLevelAtWhichAnExpressionStands(): void
    {
        $precedence = new Precedence();

        self::assertSame([Precedence::NEGATION, Precedence::PRIMARY, Precedence::CLOSED], [$precedence->top(new Not(new NumberLiteral('1'))), $precedence->top(new VariableAssignment(new UserVariable(new Name('v')), new NumberLiteral('1'))), $precedence->top(new NumberLiteral('1'))]);
    }

    public function testAbsorbsTellsWhetherAFollowingOperatorBindsIntoTheRightEdge(): void
    {
        $precedence = new Precedence();
        $truth = new VariableAssignment(new UserVariable(new Name('v')), new TruthTest(new NumberLiteral('1'), Truth::True));
        $sum = new VariableAssignment(new UserVariable(new Name('v')), new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')));

        self::assertSame([false, true, false], [$precedence->absorbs($truth, Precedence::MULTIPLICATIVE, Precedence::MULTIPLICATIVE), $precedence->absorbs($sum, Precedence::MULTIPLICATIVE, Precedence::MULTIPLICATIVE), $precedence->absorbs(new Like(new NumberLiteral('1'), new NumberLiteral('2')), Precedence::MULTIPLICATIVE, Precedence::MULTIPLICATIVE)]);
    }

    public function testFitsAcceptsALeftOperandThatKeepsItsPlace(): void
    {
        $precedence = new Precedence();
        $minus = new Unary(UnaryOperator::Minus, new NumberLiteral('1'));

        self::assertSame([true, false], [$precedence->fits($minus, Precedence::SIMPLE_EXPR, Precedence::SIMPLE_EXPR), $precedence->fits($minus, Precedence::COLLATION, Precedence::SIMPLE_EXPR)]);
    }

    public function testLeadingAnswersTheExpressionWrittenFirst(): void
    {
        $first = new NumberLiteral('1');

        self::assertSame($first, (new Precedence())->leading(new InList(new Arithmetic(ArithmeticOperator::Minus, $first, new NumberLiteral('2')), [new NumberLiteral('3')])));
    }
}
