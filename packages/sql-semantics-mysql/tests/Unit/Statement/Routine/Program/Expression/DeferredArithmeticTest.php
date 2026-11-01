<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped;

#[CoversClass(DeferredArithmetic::class)]
#[Medium]
final class DeferredArithmeticTest extends TestCase
{
    public function testDeriveScalarBindsBothOperandsWithoutReportingTheInvocationConstraint(): void
    {
        $value = new DeferredArithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new Row([new NumberLiteral('2'), new NumberLiteral('3')]));
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
        $left = new DeferredGrouped(new DeferredArithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new Row([new NumberLiteral('2'), new NumberLiteral('3')])));
        $value = new DeferredArithmetic(ArithmeticOperator::Plus, $left, new Row([new NumberLiteral('1'), new NumberLiteral('2'), new NumberLiteral('3')]));
        $problem2 = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem2);
        self::assertSame([1, 2], [$problem2->expected, $problem2->actual]);
    }

    public function testRenderRetainsTheStoredComputationThroughLowering(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() IF 1 + (2,3) THEN DO 1; END IF');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement::class, $operation->body);
        self::assertInstanceOf(DeferredArithmetic::class, $operation->body->branches[0]->condition);
        self::assertSame('CREATE PROCEDURE p() IF 1 + (2, 3) THEN DO 1; END IF', $operation->toString());
    }
    public function testOperandsRetainsThePreparationOrder(): void
    {
        $value = new DeferredArithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new Row([new NumberLiteral('2'), new NumberLiteral('3')]));
        self::assertSame([$value->left, $value->right], $value->operands());
    }
}
