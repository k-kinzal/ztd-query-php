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
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(Integral::class)]
#[Small]
final class IntegralTest extends TestCase
{
    public function testNameSpellsTheKeywordOfEachKind(): void
    {
        self::assertSame('TINYINT', (new Integral(IntegralKind::TinyInt))->name());
        self::assertSame('SMALLINT', (new Integral(IntegralKind::SmallInt))->name());
        self::assertSame('MEDIUMINT', (new Integral(IntegralKind::MediumInt))->name());
        self::assertSame('INT', (new Integral(IntegralKind::Int, '11'))->name());
        self::assertSame('BIGINT', (new Integral(IntegralKind::BigInt, '20', [NumericModifier::Unsigned]))->name());
    }

    public function testUnsignedTellsWhetherUnsignedIsWritten(): void
    {
        self::assertFalse((new Integral(IntegralKind::Int))->unsigned());
        self::assertFalse((new Integral(IntegralKind::Int, null, [NumericModifier::Signed]))->unsigned());
        self::assertTrue((new Integral(IntegralKind::Int, null, [NumericModifier::Unsigned]))->unsigned());
        self::assertTrue((new Integral(IntegralKind::Int, null, [NumericModifier::Signed, NumericModifier::Unsigned]))->unsigned());
    }

    public function testUnsignedIsTrueWhenOnlyZerofillIsWritten(): void
    {
        self::assertTrue((new Integral(IntegralKind::TinyInt, '3', [NumericModifier::Zerofill]))->unsigned());
    }

    public function testRenderWritesTheWidthAndTheAttributesInWrittenOrder(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Integral(IntegralKind::Int, '011', [NumericModifier::Zerofill, NumericModifier::Unsigned, NumericModifier::Unsigned]))->render($out);

        self::assertSame('INT(011) ZEROFILL UNSIGNED UNSIGNED', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheKeywordAloneWithoutWidthAndAttributes(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Integral(IntegralKind::BigInt))->render($out);

        self::assertSame('BIGINT', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesADecimalWidthExactlyAsWritten(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Integral(IntegralKind::SmallInt, '1.5'))->render($out);

        self::assertSame('SMALLINT(1.5)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesIntForTheNumberedAndSpelledOutSynonyms(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $int4 = $lowering->types->type($parser->parse('CREATE TABLE t (c INT4)')->find('type')[0]);
        $integer = $lowering->types->type($parser->parse('CREATE TABLE t (c INTEGER)')->find('type')[0]);
        $middleint = $lowering->types->type($parser->parse('CREATE TABLE t (c MIDDLEINT)')->find('type')[0]);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Integral::class, $int4);
        self::assertInstanceOf(Integral::class, $integer);
        self::assertInstanceOf(Integral::class, $middleint);
        self::assertSame(IntegralKind::Int, $int4->kind);
        self::assertSame(IntegralKind::Int, $integer->kind);
        self::assertSame(IntegralKind::MediumInt, $middleint->kind);
        $middleint->render($out);
        self::assertSame('MEDIUMINT', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesTheWidthAndAttributesLoweredUnderTheMiddleRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c INT(11) UNSIGNED ZEROFILL)')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Integral::class, $type);
        self::assertSame(IntegralKind::Int, $type->kind);
        self::assertSame('11', $type->width);
        self::assertSame([NumericModifier::Unsigned, NumericModifier::Zerofill], $type->modifiers);
        self::assertTrue($type->unsigned());
        $type->render($out);
        self::assertSame('INT(11) UNSIGNED ZEROFILL', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesASignedTinyintLoweredUnderTheOldestRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c TINYINT(1) SIGNED)')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Integral::class, $type);
        self::assertSame(IntegralKind::TinyInt, $type->kind);
        self::assertSame('1', $type->width);
        self::assertSame([NumericModifier::Signed], $type->modifiers);
        self::assertFalse($type->unsigned());
        $type->render($out);
        self::assertSame('TINYINT(1) SIGNED', (new Lexical())->join($out->pieces()));
    }

    public function testNegativeWidthIsRejected(): void
    {
        $this->expectExceptionMessage('A display width is an unsigned number.');

        new Integral(IntegralKind::Int, '-11');
    }

    public function testWidthWithLetterIsRejected(): void
    {
        $this->expectExceptionMessage('A display width is an unsigned number.');

        new Integral(IntegralKind::Int, '1e1');
    }
}
