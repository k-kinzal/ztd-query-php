<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Expression\OperandRule;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(OperandRule::class)]
#[Medium]
final class OperandRuleTest extends TestCase
{
    public function testScalarRefusesARowBeforePublishingAScalar(): void
    {
        $source = (new Semantics(Dialect::MySql))->parser()->parse('SELECT 1');
        $this->expectException(RejectedInput::class);
        (new OperandRule())->scalar(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), $source);
    }

    public function testCollatedKeepsNameResolutionAheadOfTheScalarUse(): void
    {
        $source = (new Semantics(Dialect::MySql))->parser()->parse('SELECT ROW(1,2) COLLATE zz');
        $this->expectException(RejectedInput::class);
        $this->expectExceptionMessage("Unknown collation: 'zz'");
        (new OperandRule())->collated(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new \SqlSemantics\Statement\Identifier\Name('zz'), $source, \SqlSemantics\Contract\GrammarRelease::MySql847);
    }

    public function testGroupedKeepsTheRowRole(): void
    {
        self::assertInstanceOf(GroupedRow::class, (new OperandRule())->grouped(new Row([new NumberLiteral('1'), new NumberLiteral('2')])));
    }

    public function testSubqueryDistinguishesExplicitMultiColumnOutput(): void
    {
        $query = new Select([], [new SelectExpression(new NumberLiteral('1')), new SelectExpression(new NumberLiteral('2'))]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\RowSubquery::class, (new OperandRule())->subquery($query));
    }

    public function testComparisonRefusesContradictoryRowShapes(): void
    {
        $source = (new Semantics(Dialect::MySql))->parser()->parse('SELECT 1');
        $this->expectException(RejectedInput::class);
        (new OperandRule())->comparison(ComparisonOperator::Equal, new NumberLiteral('1'), new Row([new NumberLiteral('1'), new NumberLiteral('2')]), $source);
    }

    public function testInListRetainsRowElements(): void
    {
        $source = (new Semantics(Dialect::MySql))->parser()->parse('SELECT 1');
        $list = (new OperandRule())->inList(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), [new Row([new NumberLiteral('1'), new NumberLiteral('2')])], false, $source);
        self::assertInstanceOf(Row::class, $list->elements[0]);
    }

    public function testInQueryKeepsAFixedMismatchInTheDeferredRole(): void
    {
        $source = (new Semantics(Dialect::MySql))->parser()->parse('SELECT 1');
        $deferred = (new OperandRule())->inQuery(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new Select([], [new SelectExpression(new NumberLiteral('1'))]), false, $source);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate::class, $deferred);
        $problem1 = $deferred->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem1);
        self::assertSame(2, $problem1->expected);
    }

    public function testQuantifiedKeepsTheScalarOnlyConstraintInTheDeferredRole(): void
    {
        $source = (new Semantics(Dialect::MySql))->parser()->parse('SELECT 1');
        $deferred = (new OperandRule())->quantified(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), ComparisonOperator::Greater, Quantifier::Any, new Select([], [new SelectExpression(new NumberLiteral('1')), new SelectExpression(new NumberLiteral('2'))]), $source);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate::class, $deferred);
        $problem2 = $deferred->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem2);
        self::assertSame(1, $problem2->expected);
    }

    public function testCheckRetainsTheTypedProblem(): void
    {
        $source = (new Semantics(Dialect::MySql))->parser()->parse('SELECT 1');
        $this->expectExceptionMessage('Operand should contain 2 column(s), not 3.');
        (new OperandRule())->check(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns(2, 3), $source);
    }

    public function testRowRefusesDeferredArithmeticAtAnOrdinaryOperandPosition(): void
    {
        $source = (new Semantics(Dialect::MySql))->parser()->parse('SELECT 1');
        $value = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus, new NumberLiteral('1'), new Row([new NumberLiteral('2'), new NumberLiteral('3')]));
        $this->expectException(RejectedInput::class);
        (new OperandRule())->row($value, $source);
    }
}
