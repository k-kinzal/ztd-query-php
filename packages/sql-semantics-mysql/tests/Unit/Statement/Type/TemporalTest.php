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
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(Temporal::class)]
#[Small]
final class TemporalTest extends TestCase
{
    public function testNameSpellsTheKeywordOfEachKind(): void
    {
        self::assertSame('DATE', (new Temporal(TemporalKind::Date))->name());
        self::assertSame('TIME', (new Temporal(TemporalKind::Time, '3'))->name());
        self::assertSame('TIMESTAMP', (new Temporal(TemporalKind::Timestamp, '6'))->name());
        self::assertSame('DATETIME', (new Temporal(TemporalKind::DateTime))->name());
        self::assertSame('YEAR', (new Temporal(TemporalKind::Year, '4', [NumericModifier::Unsigned]))->name());
    }

    public function testRenderWritesTheFractionalSecondsPrecisionAsWritten(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Temporal(TemporalKind::DateTime, '06'))->render($out);

        self::assertSame('DATETIME(06)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheKeywordAloneWithoutAPrecision(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Temporal(TemporalKind::Timestamp))->render($out);

        self::assertSame('TIMESTAMP', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheWidthAndAttributesOfYearInWrittenOrder(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Temporal(TemporalKind::Year, '4', [NumericModifier::Zerofill, NumericModifier::Signed]))->render($out);

        self::assertSame('YEAR(4) ZEROFILL SIGNED', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesEachPrecisionLoweredFromADeclaration(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $time = $lowering->types->type($parser->parse('CREATE TABLE t (c TIME(3))')->find('type')[0]);
        $timestamp = $lowering->types->type($parser->parse('CREATE TABLE t (c TIMESTAMP(6))')->find('type')[0]);
        $date = $lowering->types->type($parser->parse('CREATE TABLE t (c DATE)')->find('type')[0]);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Temporal::class, $time);
        self::assertInstanceOf(Temporal::class, $timestamp);
        self::assertInstanceOf(Temporal::class, $date);
        self::assertSame([TemporalKind::Time, '3'], [$time->kind, $time->precision]);
        self::assertSame([TemporalKind::Timestamp, '6'], [$timestamp->kind, $timestamp->precision]);
        self::assertSame([TemporalKind::Date, null], [$date->kind, $date->precision]);
        $timestamp->render($out);
        self::assertSame('TIMESTAMP(6)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesYearWithAttributesLoweredUnderTheOldestRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c YEAR(4) UNSIGNED)')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Temporal::class, $type);
        self::assertSame(TemporalKind::Year, $type->kind);
        self::assertSame('4', $type->precision);
        self::assertSame([NumericModifier::Unsigned], $type->modifiers);
        $type->render($out);
        self::assertSame('YEAR(4) UNSIGNED', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesADatetimePrecisionLoweredUnderTheMiddleRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c DATETIME(6))')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Temporal::class, $type);
        self::assertSame(TemporalKind::DateTime, $type->kind);
        self::assertSame('6', $type->precision);
        $type->render($out);
        self::assertSame('DATETIME(6)', (new Lexical())->join($out->pieces()));
    }

    public function testPrecisionOnDateIsRejected(): void
    {
        $this->expectExceptionMessage('DATE takes no precision.');

        new Temporal(TemporalKind::Date, '3');
    }

    public function testNegativePrecisionIsRejected(): void
    {
        $this->expectExceptionMessage('A precision is an unsigned number.');

        new Temporal(TemporalKind::Time, '-3');
    }

    public function testNumericAttributesOnTimestampAreRejected(): void
    {
        $this->expectExceptionMessage('Only YEAR takes numeric attributes.');

        new Temporal(TemporalKind::Timestamp, '6', [NumericModifier::Unsigned]);
    }

    public function testNumericAttributesOnDateAreRejected(): void
    {
        $this->expectExceptionMessage('Only YEAR takes numeric attributes.');

        new Temporal(TemporalKind::Date, null, [NumericModifier::Zerofill]);
    }
}
