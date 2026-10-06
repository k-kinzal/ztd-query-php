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
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CastTarget::class)]
#[Small]
final class CastTargetTest extends TestCase
{
    public function testNameSpellsTheKeywordOfEachKind(): void
    {
        self::assertSame('BINARY', (new CastTarget(CastKind::Binary, '16'))->name());
        self::assertSame('CHAR', (new CastTarget(CastKind::Char))->name());
        self::assertSame('NCHAR', (new CastTarget(CastKind::NationalChar, '5'))->name());
        self::assertSame('SIGNED', (new CastTarget(CastKind::Signed))->name());
        self::assertSame('UNSIGNED', (new CastTarget(CastKind::Unsigned))->name());
        self::assertSame('DATE', (new CastTarget(CastKind::Date))->name());
        self::assertSame('TIME', (new CastTarget(CastKind::Time, '3'))->name());
        self::assertSame('DATETIME', (new CastTarget(CastKind::DateTime, '6'))->name());
        self::assertSame('DECIMAL', (new CastTarget(CastKind::Decimal, '10', '2'))->name());
        self::assertSame('JSON', (new CastTarget(CastKind::Json))->name());
        self::assertSame('YEAR', (new CastTarget(CastKind::Year))->name());
        self::assertSame('REAL', (new CastTarget(CastKind::Real))->name());
        self::assertSame('DOUBLE', (new CastTarget(CastKind::Double))->name());
        self::assertSame('FLOAT', (new CastTarget(CastKind::Float, '24'))->name());
        self::assertSame('POINT', (new CastTarget(CastKind::Point))->name());
        self::assertSame('LINESTRING', (new CastTarget(CastKind::LineString))->name());
        self::assertSame('POLYGON', (new CastTarget(CastKind::Polygon))->name());
        self::assertSame('MULTIPOINT', (new CastTarget(CastKind::MultiPoint))->name());
        self::assertSame('MULTILINESTRING', (new CastTarget(CastKind::MultiLineString))->name());
        self::assertSame('MULTIPOLYGON', (new CastTarget(CastKind::MultiPolygon))->name());
        self::assertSame('GEOMETRYCOLLECTION', (new CastTarget(CastKind::GeometryCollection))->name());
    }

    public function testRenderWritesThePrecisionAndScaleAsWritten(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new CastTarget(CastKind::Decimal, '010', '02'))->render($out);

        self::assertSame('DECIMAL(010, 02)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheLengthAndTheCharacterSetAttributeOfChar(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new CastTarget(CastKind::Char, '10', null, new CharsetAttribute(CharsetForm::Named, new Name('utf8mb4'), BinaryMark::Trailing)))->render($out);

        self::assertSame('CHAR(10) CHARSET utf8mb4 BINARY', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheKeywordAloneWithoutOptions(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new CastTarget(CastKind::Unsigned))->render($out);

        self::assertSame('UNSIGNED', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheFractionalSecondsPrecisionOfTime(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new CastTarget(CastKind::Time, '3'))->render($out);

        self::assertSame('TIME(3)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesADecimalTargetLoweredFromACast(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('SELECT CAST(a AS DECIMAL(10,2))')->find('cast_type')[0];
        $target = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->castTarget($node);
        $out = new Output($platform->codec($profile));

        self::assertSame(CastKind::Decimal, $target->kind);
        self::assertSame(['10', '2'], [$target->length, $target->scale]);
        self::assertNull($target->charset);
        $target->render($out);
        self::assertSame('DECIMAL(10, 2)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderKeepsTheOptionalIntegerAfterSignedAndPrecisionAfterDouble(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $signed = $lowering->types->castTarget($parser->parse('SELECT CAST(a AS SIGNED INTEGER)')->find('cast_type')[0]);
        $unsigned = $lowering->types->castTarget($parser->parse('SELECT CAST(a AS UNSIGNED INT)')->find('cast_type')[0]);
        $double = $lowering->types->castTarget($parser->parse('SELECT CAST(a AS DOUBLE PRECISION)')->find('cast_type')[0]);
        $real = $lowering->types->castTarget($parser->parse('SELECT CAST(a AS REAL)')->find('cast_type')[0]);
        $out = new Output($platform->codec($profile));

        self::assertSame(CastKind::Signed, $signed->kind);
        self::assertSame(CastKind::Unsigned, $unsigned->kind);
        self::assertSame(CastKind::Double, $double->kind);
        self::assertSame(CastKind::Real, $real->kind);
        $out->list([$signed, $unsigned, $double, $real]);
        self::assertSame('SIGNED INT, UNSIGNED INT, DOUBLE PRECISION, REAL', (new Lexical())->join($out->pieces()));
        self::assertSame(OptionalWords::Written, $signed->words);
        self::assertSame(OptionalWords::Omitted, $real->words);
    }

    public function testRenderWritesGeometrycollectionForTheShortSpelling(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('SELECT CAST(a AS GEOMCOLLECTION)')->find('cast_type')[0];
        $target = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->castTarget($node);
        $out = new Output($platform->codec($profile));

        self::assertSame(CastKind::GeometryCollection, $target->kind);
        $target->render($out);
        self::assertSame('GEOMETRYCOLLECTION', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesACharTargetWithCharsetLoweredUnderTheOldestRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('SELECT CAST(a AS CHAR(10) CHARACTER SET utf8 BINARY)')->find('cast_type')[0];
        $target = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->castTarget($node);
        $out = new Output($platform->codec($profile));

        self::assertSame(CastKind::Char, $target->kind);
        self::assertSame('10', $target->length);
        self::assertInstanceOf(CharsetAttribute::class, $target->charset);
        self::assertSame(CharsetForm::CharacterSet, $target->charset->form);
        self::assertSame('utf8', $target->charset->charset?->value);
        self::assertSame(BinaryMark::Trailing, $target->charset->mark);
        $target->render($out);
        self::assertSame('CHAR(10) CHARACTER SET utf8 BINARY', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesANationalCharTargetLoweredUnderTheMiddleRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('SELECT CAST(a AS NCHAR(5))')->find('cast_type')[0];
        $target = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->castTarget($node);
        $out = new Output($platform->codec($profile));

        self::assertSame(CastKind::NationalChar, $target->kind);
        self::assertSame('5', $target->length);
        $target->render($out);
        self::assertSame('NCHAR(5)', (new Lexical())->join($out->pieces()));
    }

    public function testNegativeLengthIsRejected(): void
    {
        $this->expectExceptionMessage('A length is an unsigned number.');

        new CastTarget(CastKind::Char, '-1');
    }

    public function testLengthOnAKindThatTakesNoneIsRejected(): void
    {
        $this->expectExceptionMessage('The target kind takes no length.');

        new CastTarget(CastKind::Signed, '11');
    }

    public function testLengthOnDateIsRejected(): void
    {
        $this->expectExceptionMessage('The target kind takes no length.');

        new CastTarget(CastKind::Date, '6');
    }

    public function testScaleOnFloatIsRejected(): void
    {
        $this->expectExceptionMessage('Only DECIMAL takes a scale, written after a precision.');

        new CastTarget(CastKind::Float, '10', '2');
    }

    public function testScaleWithoutAPrecisionIsRejected(): void
    {
        $this->expectExceptionMessage('Only DECIMAL takes a scale, written after a precision.');

        new CastTarget(CastKind::Decimal, null, '2');
    }

    public function testDecimalScaleIsRejected(): void
    {
        $this->expectExceptionMessage('Only DECIMAL takes a scale, written after a precision.');

        new CastTarget(CastKind::Decimal, '10', '2.5');
    }

    public function testCharacterSetAttributeOnNationalCharIsRejected(): void
    {
        $this->expectExceptionMessage('Only CHAR takes a character set attribute.');

        new CastTarget(CastKind::NationalChar, '5', null, new CharsetAttribute(CharsetForm::Binary));
    }

    public function testCharacterSetAttributeOnBinaryIsRejected(): void
    {
        $this->expectExceptionMessage('Only CHAR takes a character set attribute.');

        new CastTarget(CastKind::Binary, null, null, new CharsetAttribute(CharsetForm::Ascii));
    }
}
