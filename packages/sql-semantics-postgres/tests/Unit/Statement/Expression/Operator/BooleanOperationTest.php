<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\OperandMismatch;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(BooleanOperation::class)]
#[Small]
final class BooleanOperationTest extends TestCase
{
    public function testDeriveScalarIsBoolean(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new BooleanOperation(BooleanOperator::And, new BooleanLiteral(true), new NullLiteral()), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testDeriveScalarReportsANonBooleanOperand(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new BooleanOperation(BooleanOperator::Or, new BooleanLiteral(true), new Constant(new IntegerConstant('1'))), $derivation->environment());
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertInstanceOf(OperandMismatch::class, $fact->type->cause);
    }

    public function testRenderWritesTheOperator(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $and = new BooleanOperation(BooleanOperator::And, new BooleanLiteral(false), new NullLiteral());
        (new BooleanOperation(BooleanOperator::Or, new BooleanLiteral(true), $and))->render($out);
        self::assertSame('TRUE OR FALSE AND NULL', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsADisjunctionInsideAConjunction(): void
    {
        $or = new BooleanOperation(BooleanOperator::Or, new BooleanLiteral(true), new NullLiteral());
        $this->expectExceptionMessage('The left operand needs parentheses to keep its place.');
        new BooleanOperation(BooleanOperator::And, $or, new NullLiteral());
    }
}
