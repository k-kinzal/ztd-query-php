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
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(UnaryOperation::class)]
#[Small]
final class UnaryOperationTest extends TestCase
{
    public function testDeriveScalarTypesANegativeConstantByItsValue(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new UnaryOperation(new OperatorName(new Name('-')), new Constant(new NumericConstant('2147483648'))), $derivation->environment());
        self::assertEquals(new Known(Builtin::Int4), $fact->type);
    }

    public function testDeriveScalarKeepsTheTypeOfANegatedNumber(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $sum = new BinaryOperation(new OperatorName(new Name('^')), new Constant(new NumericConstant('1.5')), new Constant(new IntegerConstant('2')));
        $fact = $derivation->scalar(new UnaryOperation(new OperatorName(new Name('@')), $sum), $derivation->environment());
        self::assertEquals(new Known(Builtin::Numeric), $fact->type);
    }

    public function testRenderWritesTheOperatorFirst(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new UnaryOperation(new OperatorName(new Name('-')), new UnaryOperation(new OperatorName(new Name('-')), new Constant(new IntegerConstant('1')))))->render($out);
        self::assertSame('- - 1', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAnOperandThatBindsWeaker(): void
    {
        $product = new BinaryOperation(new OperatorName(new Name('*')), new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('2')));
        $this->expectExceptionMessage('The operand needs parentheses to keep its place.');
        new UnaryOperation(new OperatorName(new Name('-')), $product);
    }

    public function testRejectsAnOperatorThatCannotBePrefix(): void
    {
        $this->expectExceptionMessage('This operator cannot be written before an operand without OPERATOR(...).');
        new UnaryOperation(new OperatorName(new Name('=')), new NullLiteral());
    }
}
