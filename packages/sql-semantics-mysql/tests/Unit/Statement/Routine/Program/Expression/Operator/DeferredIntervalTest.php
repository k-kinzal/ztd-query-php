<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredInterval;

#[CoversClass(DeferredInterval::class)]
#[Medium]
final class DeferredIntervalTest extends TestCase
{
    public function testDeriveScalarBindsBothOperandsWithoutReportingTheInvocationConstraint(): void
    {
        $value = new DeferredInterval(new NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow(new Row([new NumberLiteral('2'), new NumberLiteral('3')])), IntervalUnit::Day);
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
        $problem1 = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem1);
        self::assertSame(2, $problem1->actual);
    }

    public function testProblemFollowsNestedOperandOrder(): void
    {
        $left = new DeferredGrouped(new DeferredInterval(new NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow(new Row([new NumberLiteral('2'), new NumberLiteral('3')])), IntervalUnit::Day));
        $value = new DeferredInterval($left, new \SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow(new Row([new NumberLiteral('1'), new NumberLiteral('2'), new NumberLiteral('3')])), IntervalUnit::Month);
        $problem2 = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem2);
        self::assertSame([1, 2], [$problem2->expected, $problem2->actual]);
    }

    public function testRenderRetainsTheStoredComputationThroughLowering(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() IF 1 + INTERVAL ((2,3)) DAY THEN DO 1; END IF');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement::class, $operation->body);
        self::assertInstanceOf(DeferredInterval::class, $operation->body->branches[0]->condition);
        self::assertSame('CREATE PROCEDURE p() IF 1 + INTERVAL ((2, 3)) DAY THEN DO 1; END IF', $operation->toString());
    }
    public function testOperandsRetainsThePreparationOrder(): void
    {
        $value = new DeferredInterval(new NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow(new Row([new NumberLiteral('2'), new NumberLiteral('3')])), IntervalUnit::Day);
        self::assertSame([$value->operand, $value->quantity], $value->operands());
    }
}
