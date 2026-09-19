<?php

declare(strict_types=1);

namespace Tests\Unit\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Cursor;

#[CoversClass(Cursor::class)]
#[Small]
final class CursorTest extends TestCase
{
    public function testEof(): void
    {
        $cursor = new Cursor('ab');
        $cursor->take(2);

        self::assertTrue($cursor->eof());
        self::assertFalse((new Cursor('a'))->eof());
    }

    public function testPeek(): void
    {
        $cursor = new Cursor('abc');

        self::assertSame('a', $cursor->peek());
        self::assertSame('c', $cursor->peek(2));
        self::assertSame('', $cursor->peek(3));
    }

    public function testOffset(): void
    {
        $cursor = new Cursor('abc');
        $cursor->take(2);

        self::assertSame(2, $cursor->offset());
    }

    public function testSeek(): void
    {
        $cursor = new Cursor('abc');
        $cursor->seek(1);

        self::assertSame('b', $cursor->peek());
    }

    public function testStartsWith(): void
    {
        $cursor = new Cursor('SELECT');
        $cursor->take(2);

        self::assertTrue($cursor->startsWith('LE'));
        self::assertFalse($cursor->startsWith('le'));
    }

    public function testStartsWithIgnoringCase(): void
    {
        self::assertTrue((new Cursor('Select'))->startsWithIgnoringCase('SEL'));
        self::assertFalse((new Cursor('Select'))->startsWithIgnoringCase('SET'));
    }

    public function testTake(): void
    {
        $cursor = new Cursor('abc');

        self::assertSame('ab', $cursor->take(2));
        self::assertSame('c', $cursor->take(5));
        self::assertSame(3, $cursor->offset());
    }

    public function testMatch(): void
    {
        $cursor = new Cursor('abc123');

        self::assertSame('abc', $cursor->match('[a-z]+'));
        self::assertNull($cursor->match('[a-z]+'));
        self::assertSame('123', $cursor->match('[0-9~]+'));
    }

    public function testLookingAt(): void
    {
        $cursor = new Cursor('abc');

        self::assertTrue($cursor->lookingAt('ab'));
        self::assertFalse($cursor->lookingAt('bc'));
        self::assertSame(0, $cursor->offset());
    }

    public function testSkipPastOrEnd(): void
    {
        $cursor = new Cursor('a*/b');

        self::assertTrue($cursor->skipPastOrEnd('*/'));
        self::assertSame(3, $cursor->offset());
        self::assertFalse($cursor->skipPastOrEnd('*/'));
        self::assertTrue($cursor->eof());
    }

    public function testTakeQuoted(): void
    {
        $cursor = new Cursor("'it''s' rest");

        self::assertSame("'it''s'", $cursor->takeQuoted("'"));
        self::assertSame(' rest', $cursor->take(5));
    }

    public function testTakeQuotedWithBackslashEscapes(): void
    {
        $cursor = new Cursor("'a\\'b' x");

        self::assertSame("'a\\'b'", $cursor->takeQuoted("'", true));
        self::assertNull((new Cursor("'open"))->takeQuoted("'"));
        self::assertNull((new Cursor("'open\\'"))->takeQuoted("'", true));
    }
}
