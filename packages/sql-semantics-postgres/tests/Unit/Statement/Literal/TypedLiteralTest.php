<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\TypedLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\ArrayBound;
use SqlSemantics\Platform\PostgreSql\Statement\Type\ArraySpecifier;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\BitDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\CharacterDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\CharacterKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalFields;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(TypedLiteral::class)]
#[Small]
final class TypedLiteralTest extends TestCase
{
    public function testOutputNameIsTheCatalogNameOfTheType(): void
    {
        self::assertSame('float8', (new TypedLiteral(new TypeName(new KeywordDesignation(TypeKeyword::DoublePrecision)), new StringConstant('1')))->outputName()->value);
    }

    public function testDeriveScalarDerivesTheModifiersAndTheType(): void
    {
        $modifier = new Constant(new IntegerConstant('3'));
        $literal = new TypedLiteral(new TypeName(new BitDesignation(true, [$modifier])), new StringConstant('101'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar($literal, $derivation->environment());
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertSame('bit varying(3)', $fact->type->descriptor->name());
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertTrue($derivation->facts()->covers($modifier));
    }

    public function testDeriveScalarLeavesBitAndCharacterWithoutALengthUnconstrained(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new TypedLiteral(new TypeName(new CharacterDesignation(CharacterKeyword::Char)), new StringConstant('abc')), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bpchar), $fact->type);
    }

    public function testDeriveScalarReportsInvalidModifiers(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new TypedLiteral(new TypeName(new DecimalDesignation(DecimalKeyword::Numeric, [new Constant(new IntegerConstant('0'))])), new StringConstant('1')), $derivation->environment());
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertCount(1, $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheTypeThenTheString(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new TypedLiteral(new TypeName(new NamedDesignation(new DottedName([new Name('pg_catalog'), new Name('date')]))), new StringConstant('2024-01-01')))->render($out);
        self::assertSame("pg_catalog.date '2024-01-01'", (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesIntervalFieldsAfterTheString(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new TypedLiteral(new TypeName(new IntervalDesignation(IntervalFields::YearToMonth)), new StringConstant('1-2')))->render($out);
        self::assertSame("INTERVAL '1-2' YEAR TO MONTH", (new Lexical())->join($out->pieces()));
        $second = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new TypedLiteral(new TypeName(new IntervalDesignation(null, new IntegerConstant('3'))), new StringConstant('1')))->render($second);
        self::assertSame("INTERVAL (3) '1'", (new Lexical())->join($second->pieces()));
    }

    public function testRejectsAnArrayType(): void
    {
        $this->expectExceptionMessage('A typed constant names a plain type.');
        new TypedLiteral(new TypeName(new KeywordDesignation(TypeKeyword::Int), false, new ArraySpecifier([new ArrayBound()])), new StringConstant('{}'));
    }
}
