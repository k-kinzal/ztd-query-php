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
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(Decimal::class)]
#[Small]
final class DecimalTest extends TestCase
{
    public function testNameIsDecimalWhateverIsWritten(): void
    {
        self::assertSame('DECIMAL', (new Decimal())->name());
        self::assertSame('DECIMAL', (new Decimal('10'))->name());
        self::assertSame('DECIMAL', (new Decimal('10', '2', [NumericModifier::Unsigned]))->name());
    }

    public function testRenderWritesThePrecisionTheScaleAndTheAttributesInWrittenOrder(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Decimal('010', '02', [NumericModifier::Zerofill, NumericModifier::Unsigned]))->render($out);

        self::assertSame('DECIMAL(010, 02) ZEROFILL UNSIGNED', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesThePrecisionAloneWithoutAScale(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Decimal('5'))->render($out);

        self::assertSame('DECIMAL(5)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheKeywordAloneWithoutOptions(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Decimal())->render($out);

        self::assertSame('DECIMAL', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesDecimalForTheSynonymsDecAndNumericAndFixed(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $dec = $lowering->types->type($parser->parse('CREATE TABLE t (c DEC(10,2))')->find('type')[0]);
        $numeric = $lowering->types->type($parser->parse('CREATE TABLE t (c NUMERIC(5))')->find('type')[0]);
        $fixed = $lowering->types->type($parser->parse('CREATE TABLE t (c FIXED)')->find('type')[0]);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Decimal::class, $dec);
        self::assertInstanceOf(Decimal::class, $numeric);
        self::assertInstanceOf(Decimal::class, $fixed);
        self::assertSame(['10', '2'], [$dec->precision, $dec->scale]);
        self::assertSame(['5', null], [$numeric->precision, $numeric->scale]);
        self::assertSame([null, null], [$fixed->precision, $fixed->scale]);
        $dec->render($out);
        self::assertSame('DECIMAL(10, 2)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesTheAttributesLoweredUnderTheOldestRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c DECIMAL(10,2) UNSIGNED ZEROFILL)')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Decimal::class, $type);
        self::assertSame([NumericModifier::Unsigned, NumericModifier::Zerofill], $type->modifiers);
        $type->render($out);
        self::assertSame('DECIMAL(10, 2) UNSIGNED ZEROFILL', (new Lexical())->join($out->pieces()));
    }

    public function testNegativePrecisionIsRejected(): void
    {
        $this->expectExceptionMessage('A precision is an unsigned number.');

        new Decimal('-10');
    }

    public function testScaleWithoutAPrecisionIsRejected(): void
    {
        $this->expectExceptionMessage('A scale is an unsigned integer written after a precision.');

        new Decimal(null, '2');
    }

    public function testDecimalScaleIsRejected(): void
    {
        $this->expectExceptionMessage('A scale is an unsigned integer written after a precision.');

        new Decimal('10', '2.5');
    }
}
