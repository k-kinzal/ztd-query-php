<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(Binary::class)]
#[Medium]
final class BinaryTest extends TestCase
{
    public function testNameSpellsTheKeywordsOfEachKind(): void
    {
        self::assertSame('BINARY', (new Binary(BinaryKind::Binary, '16'))->name());
        self::assertSame('VARBINARY', (new Binary(BinaryKind::VarBinary, '16'))->name());
        self::assertSame('TINYBLOB', (new Binary(BinaryKind::TinyBlob))->name());
        self::assertSame('BLOB', (new Binary(BinaryKind::Blob))->name());
        self::assertSame('MEDIUMBLOB', (new Binary(BinaryKind::MediumBlob))->name());
        self::assertSame('LONGBLOB', (new Binary(BinaryKind::LongBlob))->name());
        self::assertSame('LONG VARBINARY', (new Binary(BinaryKind::LongVarBinary))->name());
    }

    public function testRenderWritesTheKeywordsAndTheLengthAsWritten(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Binary(BinaryKind::VarBinary, '016'))->render($out);

        self::assertSame('VARBINARY(016)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheTwoKeywordsOfLongVarbinary(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Binary(BinaryKind::LongVarBinary))->render($out);

        self::assertSame('LONG VARBINARY', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesNoParenthesesWithoutALength(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Binary(BinaryKind::Binary))->render($out);

        self::assertSame('BINARY', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesABlobWithALengthLoweredFromADeclaration(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c BLOB(100))')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Binary::class, $type);
        self::assertSame(BinaryKind::Blob, $type->kind);
        self::assertSame('100', $type->length);
        $type->render($out);
        self::assertSame('BLOB(100)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesLongVarbinaryLoweredUnderTheOldestRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c LONG VARBINARY)')->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Binary::class, $type);
        self::assertSame(BinaryKind::LongVarBinary, $type->kind);
        self::assertNull($type->length);
        $type->render($out);
        self::assertSame('LONG VARBINARY', (new Lexical())->join($out->pieces()));
    }

    public function testLengthOnATinyBlobIsRejected(): void
    {
        $this->expectExceptionMessage('Only BINARY, VARBINARY and BLOB take a length.');

        new Binary(BinaryKind::TinyBlob, '255');
    }

    public function testLengthOnLongVarbinaryIsRejected(): void
    {
        $this->expectExceptionMessage('Only BINARY, VARBINARY and BLOB take a length.');

        new Binary(BinaryKind::LongVarBinary, '1');
    }

    public function testNegativeLengthIsRejected(): void
    {
        $this->expectExceptionMessage('A length is an unsigned number.');

        new Binary(BinaryKind::VarBinary, '-1');
    }

    public function testEmptyLengthIsRejected(): void
    {
        $this->expectExceptionMessage('A length is an unsigned number.');

        new Binary(BinaryKind::Blob, '');
    }
}
