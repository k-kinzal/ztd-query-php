<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\SourceReader;

#[CoversClass(SourceReader::class)]
#[Small]
final class SourceReaderTest extends TestCase
{
    public function testEof(): void
    {
        $reader = new SourceReader('ab');
        $reader->take(2);

        self::assertTrue($reader->eof());
        self::assertFalse((new SourceReader('a'))->eof());
    }

    public function testPeek(): void
    {
        $reader = new SourceReader('ab');

        self::assertSame('a', $reader->peek());
        self::assertSame('b', $reader->peek(1));
        self::assertSame('', $reader->peek(2));
    }

    public function testStartsWith(): void
    {
        $reader = new SourceReader('%token X');
        $reader->take(1);

        self::assertTrue($reader->startsWith('token'));
        self::assertFalse($reader->startsWith('%'));
    }

    public function testTake(): void
    {
        $reader = new SourceReader('abc');

        self::assertSame('ab', $reader->take(2));
        self::assertSame('c', $reader->take(9));
    }

    public function testMatch(): void
    {
        $reader = new SourceReader('abc // x');

        self::assertSame('abc', $reader->match('[a-z]+'));
        self::assertNull($reader->match('[a-z]+'));
        self::assertSame(' ', $reader->match('\s'));
        self::assertSame('// x', $reader->match('//[^\n]*'));
    }

    public function testSkipPast(): void
    {
        $reader = new SourceReader('a*/b');

        self::assertTrue($reader->skipPast('*/'));
        self::assertSame(3, $reader->offset());
        self::assertFalse($reader->skipPast('*/'));
        self::assertTrue($reader->eof());
    }

    public function testOffset(): void
    {
        $reader = new SourceReader('abc');
        $reader->take(1);

        self::assertSame(1, $reader->offset());
    }

    public function testLine(): void
    {
        $reader = new SourceReader("a\nb\nc");
        $reader->take(4);

        self::assertSame(3, $reader->line());
        self::assertSame(1, (new SourceReader('x'))->line());
    }
}
