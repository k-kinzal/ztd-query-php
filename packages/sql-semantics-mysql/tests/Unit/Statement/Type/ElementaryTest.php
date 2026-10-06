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
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(Elementary::class)]
#[Small]
final class ElementaryTest extends TestCase
{
    public function testNameSpellsTheKeywordOfEachKind(): void
    {
        self::assertSame('BOOL', (new Elementary(ElementaryKind::Boolean))->name());
        self::assertSame('SERIAL', (new Elementary(ElementaryKind::Serial))->name());
        self::assertSame('JSON', (new Elementary(ElementaryKind::Json))->name());
        self::assertSame('BIT', (new Elementary(ElementaryKind::Bit, '8'))->name());
        self::assertSame('VECTOR', (new Elementary(ElementaryKind::Vector, '3'))->name());
    }

    public function testRenderWritesTheKeywordAndTheLengthAsWritten(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Elementary(ElementaryKind::Bit, '08'))->render($out);

        self::assertSame('BIT(08)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheKeywordAloneWithoutALength(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Elementary(ElementaryKind::Serial))->render($out);

        self::assertSame('SERIAL', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesBoolForTheBooleanSpelling(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c BOOLEAN)')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Elementary::class, $type);
        self::assertSame(ElementaryKind::Boolean, $type->kind);
        self::assertNull($type->length);
        $type->render($out);
        self::assertSame('BOOL', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesAVectorWithItsLengthUnderTheNewestRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-9.1.0', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c VECTOR(3))')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Elementary::class, $type);
        self::assertSame(ElementaryKind::Vector, $type->kind);
        self::assertSame('3', $type->length);
        $type->render($out);
        self::assertSame('VECTOR(3)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesABitWithItsLengthUnderTheMiddleRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c BIT(64))')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Elementary::class, $type);
        self::assertSame(ElementaryKind::Bit, $type->kind);
        self::assertSame('64', $type->length);
        $type->render($out);
        self::assertSame('BIT(64)', (new Lexical())->join($out->pieces()));
    }

    public function testLengthOnJsonIsRejected(): void
    {
        $this->expectExceptionMessage('Only BIT and VECTOR take a length.');

        new Elementary(ElementaryKind::Json, '1');
    }

    public function testLengthOnBoolIsRejected(): void
    {
        $this->expectExceptionMessage('Only BIT and VECTOR take a length.');

        new Elementary(ElementaryKind::Boolean, '1');
    }

    public function testSignedLengthIsRejected(): void
    {
        $this->expectExceptionMessage('A length is an unsigned number.');

        new Elementary(ElementaryKind::Bit, '+8');
    }
}
