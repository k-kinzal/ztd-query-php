<?php

declare(strict_types=1);

namespace Tests\Unit\Scanner;

use BisonParser\Ast\Location;
use BisonParser\Scanner\Cursor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cursor::class)]
#[UsesClass(Location::class)]
#[Small]
final class CursorTest extends TestCase
{
    public function testEof(): void
    {
        $cursor = new Cursor('ab');

        self::assertFalse($cursor->eof());
        $cursor->take(2);
        self::assertTrue($cursor->eof());
        self::assertTrue((new Cursor(''))->eof());
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
        $cursor = new Cursor("a\nbc");

        self::assertSame(0, $cursor->offset());
        $cursor->take(3);
        self::assertSame(3, $cursor->offset());
    }

    public function testLocation(): void
    {
        $cursor = new Cursor("ab\ncd\n\nef");

        self::assertSame('1:1', (string) $cursor->location());
        $cursor->take(1);
        self::assertSame('1:2', (string) $cursor->location());
        $cursor->take(3);
        self::assertSame('2:2', (string) $cursor->location());
        $cursor->take(4);
        self::assertSame('4:2', (string) $cursor->location());
    }

    public function testStartsWith(): void
    {
        $cursor = new Cursor('%%rest');

        self::assertTrue($cursor->startsWith('%%'));
        self::assertFalse($cursor->startsWith('%{'));
        $cursor->take(2);
        self::assertTrue($cursor->startsWith('rest'));
        self::assertFalse($cursor->startsWith('rest and more'));
    }

    public function testTake(): void
    {
        $cursor = new Cursor('abc');

        self::assertSame('ab', $cursor->take(2));
        self::assertSame('c', $cursor->take(5));
        self::assertSame('', $cursor->take(1));
        self::assertSame(3, $cursor->offset());
    }

    public function testMatch(): void
    {
        $cursor = new Cursor('foo~bar 12');

        self::assertNull($cursor->match('[0-9]+'));
        self::assertSame('foo~bar', $cursor->match('[a-z]+~[a-z]+'));
        self::assertSame(7, $cursor->offset());
        self::assertSame(' ', $cursor->take(1));
        self::assertSame('12', $cursor->match('[0-9]+'));
    }

    public function testLookingAt(): void
    {
        $cursor = new Cursor('ab12');

        self::assertTrue($cursor->lookingAt('[a-z]{2}'));
        self::assertFalse($cursor->lookingAt('[0-9]'));
        self::assertSame(0, $cursor->offset());
        self::assertTrue((new Cursor('a~b'))->lookingAt('a~b'));
    }

    public function testTakeUntil(): void
    {
        $cursor = new Cursor("/* a\nb */ tail");

        self::assertSame('/* a', $cursor->takeUntil("\n"));
        self::assertSame('b ', $cursor->takeUntil('*/'));
        self::assertSame('2:5', (string) $cursor->location());
        self::assertNull($cursor->takeUntil('*/'));
        self::assertSame(' tail', $cursor->take(5));
    }
}
