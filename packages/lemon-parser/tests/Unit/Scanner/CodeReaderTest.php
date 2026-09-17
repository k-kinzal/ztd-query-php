<?php

declare(strict_types=1);

namespace Tests\Unit\Scanner;

use LemonParser\Ast\Location;
use LemonParser\Scanner\CodeReader;
use LemonParser\Scanner\Cursor;
use LemonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CodeReader::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Location::class)]
#[UsesClass(SyntaxException::class)]
#[Small]
final class CodeReaderTest extends TestCase
{
    public function testRead(): void
    {
        $cursor = new Cursor("{ if (a) { b(\"}\"); } /* } */ // }\n c('}') } tail");

        self::assertSame(" if (a) { b(\"}\"); } /* } */ // }\n c('}') ", (new CodeReader())->read($cursor));
        self::assertSame(' tail', $cursor->take(5));
    }

    public function testReadRejectsAnUnterminatedBlock(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('C code starting on this line is not terminated before the end of the file. at 1:1');

        (new CodeReader())->read(new Cursor("{ a { b }\n"));
    }

    public function testComment(): void
    {
        $reader = new CodeReader();
        $line = new Cursor("// x }\n}");
        $block = new Cursor('/* } */}');
        $open = new Cursor('/* never closed');

        $reader->comment($line);
        $reader->comment($block);
        $reader->comment($open);

        self::assertSame('}', $line->take(1));
        self::assertSame('}', $block->take(1));
        self::assertTrue($open->eof());
    }

    public function testLiteral(): void
    {
        $reader = new CodeReader();
        $string = new Cursor('"a\\"b" }');
        $character = new Cursor("'\\\\' }");
        $open = new Cursor('"never closed');

        $reader->literal($string);
        $reader->literal($character);
        $reader->literal($open);

        self::assertSame(' }', $string->take(2));
        self::assertSame(' }', $character->take(2));
        self::assertTrue($open->eof());
    }
}
