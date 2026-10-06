<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Literal\ByteSize;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Rendering\PieceKind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ByteSize::class)]
#[Small]
final class ByteSizeTest extends TestCase
{
    public function testRenderWritesTheNumber(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new ByteSize(new Numeral('1024')))->render($out);

        self::assertSame('1024', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheWordAsOneName(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new ByteSize(null, new Name('16M')))->render($out);
        $pieces = $out->pieces();

        self::assertCount(1, $pieces);
        self::assertSame(PieceKind::Name, $pieces[0]->kind);
        self::assertSame('`16M`', $pieces[0]->text);
    }

    public function testRenderReadsBackAsTheSameWord(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $size = $lowering->numbers->size($parser->parse("CREATE TABLESPACE ts ADD DATAFILE 'f' INITIAL_SIZE = 16M")->find('size_number')[0]);
        $out = new Output($platform->codec($profile));
        $size->render($out);
        $again = $lowering->numbers->size($parser->parse("CREATE TABLESPACE ts ADD DATAFILE 'f' INITIAL_SIZE = " . (new Lexical())->join($out->pieces()))->find('size_number')[0]);

        self::assertNull($size->number);
        self::assertSame('16M', $size->word?->value);
        self::assertSame('16M', $again->word?->value);
    }

    public function testLoweredHexadecimalSizeKeepsItsDigits(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse("CREATE TABLESPACE ts ADD DATAFILE 'f' INITIAL_SIZE = 0x400")->find('size_number')[0];
        $size = (new Lowering($platform->productions($profile), new Leaves(), $profile))->numbers->size($node);
        $out = new Output($platform->codec($profile));
        $size->render($out);

        self::assertNull($size->word);
        self::assertSame('400', $size->number?->text);
        self::assertTrue($size->number->hexadecimal);
        self::assertSame('0x400', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsASizeWithNeitherNumberNorWord(): void
    {
        $this->expectExceptionMessage('A size is either a number or a word.');

        new ByteSize(null);
    }

    public function testRejectsASizeWithBothNumberAndWord(): void
    {
        $this->expectExceptionMessage('A size is either a number or a word.');

        new ByteSize(new Numeral('16'), new Name('16M'));
    }
}
