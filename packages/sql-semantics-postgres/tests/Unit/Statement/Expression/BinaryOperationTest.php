<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(BinaryOperation::class)]
#[Small]
final class BinaryOperationTest extends TestCase
{
    public function testOutputNameIsNone(): void
    {
        self::assertNull((new BinaryOperation(new OperatorName(new Name('=')), new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('2'))))->outputName());
    }

    public function testDeriveScalarIsBooleanForCatalogComparisons(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new BinaryOperation(new OperatorName(new Name('<')), new Constant(new IntegerConstant('1')), new Constant(new StringConstant('2'))), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testDeriveScalarIsNullableWhenAnOperandIsNull(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new BinaryOperation(new OperatorName(new Name('=')), new Constant(new IntegerConstant('1')), new NullLiteral()), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testDeriveScalarPromotesAnIntegerAndANumeric(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new BinaryOperation(new OperatorName(new Name('*')), new Constant(new IntegerConstant('2')), new Constant(new NumericConstant('1', '5', null))), $derivation->environment());
        self::assertEquals(new Known(Builtin::Numeric), $fact->type);
    }

    public function testDeriveScalarDependsOnAnOperatorOutsideTheCatalog(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new BinaryOperation(new OperatorName(new Name('<->')), new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('2'))), $derivation->environment());
        self::assertEquals(new Dependent([new UndeclaredRoutine(new QualifiedName(new Name('<->')))]), $fact->type);
    }

    public function testRenderWritesTheOperatorBetweenTheOperands(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new BinaryOperation(new OperatorName(new Name('+')), new Constant(new IntegerConstant('1')), new BinaryOperation(new OperatorName(new Name('*')), new Constant(new IntegerConstant('2')), new Constant(new IntegerConstant('3')))))->render($out);
        self::assertSame('1 + 2 * 3', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsALeftOperandThatBindsWeaker(): void
    {
        $sum = new BinaryOperation(new OperatorName(new Name('+')), new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('2')));
        $this->expectExceptionMessage('The left operand needs parentheses to keep its place.');
        new BinaryOperation(new OperatorName(new Name('*')), $sum, new Constant(new IntegerConstant('3')));
    }

    public function testRejectsARightOperandOfTheSameLevel(): void
    {
        $difference = new BinaryOperation(new OperatorName(new Name('-')), new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('2')));
        $this->expectExceptionMessage('The right operand needs parentheses to keep its place.');
        new BinaryOperation(new OperatorName(new Name('-')), new Constant(new IntegerConstant('3')), $difference);
    }

    public function testAcceptsAGroupedOperand(): void
    {
        $sum = new BinaryOperation(new OperatorName(new Name('+')), new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('2')));
        self::assertInstanceOf(Grouped::class, (new BinaryOperation(new OperatorName(new Name('*')), new Grouped($sum), new Constant(new IntegerConstant('3'))))->left);
    }

    public function testRejectsAQualifiedOperatorWithoutTheOperatorSyntax(): void
    {
        $this->expectExceptionMessage('An expression writes a qualified operator with OPERATOR(...).');
        new BinaryOperation(new OperatorName(new Name('+'), [new Name('pg_catalog')]), new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('2')));
    }
}
