<?php

declare(strict_types=1);

namespace Tests\Unit\Scanner;

use BisonParser\Ast\Location;
use BisonParser\Scanner\CodeReader;
use BisonParser\Scanner\Cursor;
use BisonParser\SyntaxException;
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
    public function testBraced(): void
    {
        $reader = new CodeReader();
        $cursor = new Cursor('{ if (a) { b("}"); } /* } */ <% c %> } tail');

        self::assertSame(' if (a) { b("}"); } /* } */ <% c %> ', $reader->braced($cursor));
        self::assertSame(' tail', $cursor->take(5));
    }

    public function testBracedNeverClosesOnADigraph(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Missing '}' closing the braced code opened at 1:1");

        (new CodeReader())->braced(new Cursor('{ x %> y } z'));
    }

    public function testBracedClosesOnTheBraceThatBalancesADigraph(): void
    {
        $cursor = new Cursor('{ x %> { y } } z');

        self::assertSame(' x %> { y ', (new CodeReader())->braced($cursor));
        self::assertSame(' } z', $cursor->take(4));
    }

    public function testBracedSplicesBackslashNewlines(): void
    {
        $cursor = new Cursor("{ a /\\\n* } *\\\n\\\n/ b <\\\n% x %\\\n> c <% } } tail");

        self::assertSame(" a /\\\n* } *\\\n\\\n/ b <\\\n% x %\\\n> c <% } ", (new CodeReader())->braced($cursor));
        self::assertSame(' tail', $cursor->take(5));
    }

    public function testBracedRejectsAnUnterminatedBlock(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Missing '}' closing the braced code opened at 1:1");

        (new CodeReader())->braced(new Cursor("{ a { b }\n"));
    }

    public function testPrologue(): void
    {
        $cursor = new Cursor("%{\n#define X \"%}\" // %}\n%} rest");

        self::assertSame("\n#define X \"%}\" // %}\n", (new CodeReader())->prologue($cursor));
        self::assertSame(' rest', $cursor->take(5));
    }

    public function testPrologueRejectsAnUnterminatedBlock(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Missing '%}' closing the prologue opened at 1:1");

        (new CodeReader())->prologue(new Cursor("%{ int x;\n"));
    }

    public function testUnit(): void
    {
        $reader = new CodeReader();

        self::assertSame('/* } */', $reader->unit(new Cursor('/* } */ x')));
        self::assertSame('// } to the end', $reader->unit(new Cursor("// } to the end\n}")));
        self::assertSame('"a\"}"', $reader->unit(new Cursor('"a\"}" tail')));
        self::assertSame("'}'", $reader->unit(new Cursor("'}' tail")));
        self::assertSame('<%', $reader->unit(new Cursor('<% x')));
        self::assertSame('%>', $reader->unit(new Cursor('%> x')));
        self::assertSame('<<', $reader->unit(new Cursor('<<% x')));
        self::assertSame("<\\\n%", $reader->unit(new Cursor("<\\\n% x")));
        self::assertSame("// a \\\n still the comment", $reader->unit(new Cursor("// a \\\n still the comment\n}")));
        self::assertSame('"a\\[b"', $reader->unit(new Cursor('"a\\[b" tail')));
        self::assertSame("\"a\\\n\"", $reader->unit(new Cursor("\"a\\\n\"b\" tail")));
        self::assertSame("\"a\\\\\n\"b\"", $reader->unit(new Cursor("\"a\\\\\n\"b\" tail")));
        self::assertSame('a', $reader->unit(new Cursor('abc')));
    }

    public function testOpens(): void
    {
        $reader = new CodeReader();

        self::assertTrue($reader->opens('{'));
        self::assertTrue($reader->opens('<%'));
        self::assertTrue($reader->opens("<\\\n%"));
        self::assertFalse($reader->opens('}'));
        self::assertFalse($reader->opens('<<'));
    }

    public function testCloses(): void
    {
        $reader = new CodeReader();

        self::assertTrue($reader->closes('}'));
        self::assertTrue($reader->closes('%>'));
        self::assertTrue($reader->closes("%\\\n>"));
        self::assertFalse($reader->closes('{'));
    }

    public function testUnitRejectsAnUnterminatedComment(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Missing '*/' closing the comment opened at 1:1");

        (new CodeReader())->unit(new Cursor('/* never closed'));
    }

    public function testUnitRejectsAnUnterminatedString(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Missing '\"' closing the string opened at 1:1");

        (new CodeReader())->unit(new Cursor("\"broken\n\""));
    }

    public function testUnitRejectsAnUnterminatedCharacterLiteral(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Missing ''' closing the character literal opened at 1:1");

        (new CodeReader())->unit(new Cursor("'"));
    }
}
