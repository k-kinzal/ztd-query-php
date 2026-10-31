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

#[CoversClass(DeferredGrouped::class)]
#[Medium]
final class DeferredGroupedTest extends TestCase
{
    public function testRenderRetainsAnOdbcEscapeKindAroundTheOperand(): void
    {
        $operand = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone(new NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('+00:00'));
        $value = new DeferredGrouped($operand, new \SqlSemantics\Statement\Identifier\Name('foo'));
        $semantics = new Semantics(Dialect::MySql);
        $out = new \SqlSemantics\Rendering\Output((new \SqlSemantics\Platform\MySql\Platform())->codec($semantics->profile()));
        $value->render($out);
        self::assertSame($operand, $value->operand);
        self::assertStringStartsWith('{ foo ', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }

    public function testDeriveScalarPreservesTheDeferredConstraint(): void
    {
        $value = new DeferredGrouped(new DeferredArithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new Row([new NumberLiteral('2'), new NumberLiteral('3')])));
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testProblemKeepsTheEnclosedWidth(): void
    {
        $value = new DeferredGrouped(new DeferredArithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new Row([new NumberLiteral('2'), new NumberLiteral('3')])));
        $problem1 = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem1);
        self::assertSame([1, 2], [$problem1->expected, $problem1->actual]);
    }

    public function testRenderKeepsTheParentheses(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() IF (1 + (2,3)) THEN DO 1; END IF');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement::class, $operation->body);
        self::assertInstanceOf(DeferredGrouped::class, $operation->body->branches[0]->condition);
        self::assertSame('CREATE PROCEDURE p() IF (1 + (2, 3)) THEN DO 1; END IF', $operation->toString());
    }
    public function testOperandsRetainsThePreparationOrder(): void
    {
        $value = new DeferredArithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new Row([new NumberLiteral('2'), new NumberLiteral('3')]));
        self::assertSame([$value], (new DeferredGrouped($value))->operands());
    }
}
