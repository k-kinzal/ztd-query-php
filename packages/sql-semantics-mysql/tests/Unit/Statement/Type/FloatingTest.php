<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(Floating::class)]
#[Small]
final class FloatingTest extends TestCase
{
    public function testNameSpellsTheKeywordOfEachKind(): void
    {
        self::assertSame('FLOAT', (new Floating(FloatingKind::Float, '24'))->name());
        self::assertSame('REAL', (new Floating(FloatingKind::Real))->name());
        self::assertSame('DOUBLE', (new Floating(FloatingKind::Double, '10', '2'))->name());
    }

    public function testRenderWritesThePrecisionTheScaleAndTheAttributesInWrittenOrder(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Floating(FloatingKind::Double, '010', '02', [NumericModifier::Zerofill, NumericModifier::Unsigned]))->render($out);

        self::assertSame('DOUBLE(010, 02) ZEROFILL UNSIGNED', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesThePrecisionAloneWithoutAScale(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Floating(FloatingKind::Float, '24'))->render($out);

        self::assertSame('FLOAT(24)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheKeywordAloneWithoutOptions(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Floating(FloatingKind::Real))->render($out);

        self::assertSame('REAL', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesDoubleForDoublePrecisionAndFloat8(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $precision = $lowering->types->type($parser->parse('CREATE TABLE t (c DOUBLE PRECISION)')->find('type')[0]);
        $float8 = $lowering->types->type($parser->parse('CREATE TABLE t (c FLOAT8)')->find('type')[0]);
        $float4 = $lowering->types->type($parser->parse('CREATE TABLE t (c FLOAT4)')->find('type')[0]);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Floating::class, $precision);
        self::assertInstanceOf(Floating::class, $float8);
        self::assertInstanceOf(Floating::class, $float4);
        self::assertSame(FloatingKind::Double, $precision->kind);
        self::assertSame(FloatingKind::Double, $float8->kind);
        self::assertSame(FloatingKind::Float, $float4->kind);
        self::assertNull($precision->precision);
        $precision->render($out);
        self::assertSame('DOUBLE', (new Lexical())->join($out->pieces()));
    }

    public function testRenderKeepsRealApartFromDouble(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c REAL(8,2) UNSIGNED)')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Floating::class, $type);
        self::assertSame(FloatingKind::Real, $type->kind);
        self::assertSame(['8', '2'], [$type->precision, $type->scale]);
        self::assertSame([NumericModifier::Unsigned], $type->modifiers);
        $type->render($out);
        self::assertSame('REAL(8, 2) UNSIGNED', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesTheDisplayFormOfFloatUnderTheMiddleRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c FLOAT(7,3))')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Floating::class, $type);
        self::assertSame(FloatingKind::Float, $type->kind);
        self::assertSame(['7', '3'], [$type->precision, $type->scale]);
        $type->render($out);
        self::assertSame('FLOAT(7, 3)', (new Lexical())->join($out->pieces()));
    }

    public function testNegativePrecisionIsRejected(): void
    {
        $this->expectExceptionMessage('A precision is an unsigned number.');

        new Floating(FloatingKind::Float, '-24');
    }

    public function testScaleWithoutAPrecisionIsRejected(): void
    {
        $this->expectExceptionMessage('A scale is an unsigned integer written after a precision.');

        new Floating(FloatingKind::Double, null, '2');
    }

    public function testDecimalScaleIsRejected(): void
    {
        $this->expectExceptionMessage('A scale is an unsigned integer written after a precision.');

        new Floating(FloatingKind::Double, '10', '.5');
    }
}
