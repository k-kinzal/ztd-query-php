<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\SourceCursor;

#[CoversClass(SourceCursor::class)]
final class SourceCursorTest extends TestCase
{
    public function testAtEndIsTrueForAnEmptySource(): void
    {
        self::assertTrue((new SourceCursor(''))->atEnd());
    }

    public function testAtEndIsFalseWhileACharacterRemains(): void
    {
        self::assertFalse((new SourceCursor('a'))->atEnd());
    }

    public function testOffsetStartsAtTheBeginningOfTheSource(): void
    {
        self::assertSame(0, (new SourceCursor('abc'))->offset());
    }

    public function testOffsetCountsWhatHasBeenConsumed(): void
    {
        $cursor = new SourceCursor('abc');

        $cursor->advance(2);

        self::assertSame(2, $cursor->offset());
    }

    public function testCurrentDoesNotConsumeTheCharacterItReports(): void
    {
        $cursor = new SourceCursor('ab');

        self::assertSame('a', $cursor->current());
        self::assertSame(0, $cursor->offset());
    }

    public function testCurrentIsEmptyAtTheEndOfTheSource(): void
    {
        self::assertSame('', (new SourceCursor(''))->current());
    }

    public function testPeekReportsTheCharacterAfterTheCursor(): void
    {
        self::assertSame('b', (new SourceCursor('ab'))->peek());
    }

    public function testPeekReportsNothingPastTheLastCharacter(): void
    {
        self::assertNull((new SourceCursor('a'))->peek());
    }

    public function testStartsWithComparesFromTheCursorOnwards(): void
    {
        $cursor = new SourceCursor('%{ code %}');

        $cursor->advance();

        self::assertTrue($cursor->startsWith('{ code'));
    }

    public function testStartsWithIgnoresWhatTheCursorHasPassed(): void
    {
        $cursor = new SourceCursor('%{ code %}');

        $cursor->advance();

        self::assertFalse($cursor->startsWith('%{'));
    }

    public function testAdvanceMovesTheCursorForward(): void
    {
        $cursor = new SourceCursor('abc');

        $cursor->advance();

        self::assertSame('b', $cursor->current());
    }

    public function testAdvanceStopsAtTheEndOfTheSource(): void
    {
        $cursor = new SourceCursor('ab');

        $cursor->advance(99);

        self::assertSame(2, $cursor->offset());
    }

    public function testSkipWhitespaceStopsAtTheFirstOtherCharacter(): void
    {
        $cursor = new SourceCursor("  \n\t x");

        $cursor->skipWhitespace();

        self::assertSame('x', $cursor->current());
    }

    public function testSkipWhitespaceLeavesANonBlankCursorAlone(): void
    {
        $cursor = new SourceCursor('x  ');

        $cursor->skipWhitespace();

        self::assertSame(0, $cursor->offset());
    }

    public function testTakeWhileReturnsTheAcceptedRun(): void
    {
        $cursor = new SourceCursor('abc123');

        self::assertSame('abc', $cursor->takeWhile(static fn (string $c): bool => ctype_alpha($c)));
        self::assertSame('123', $cursor->takeRest());
    }

    public function testTakeWhileConsumesNothingWhenTheFirstCharacterIsRejected(): void
    {
        $cursor = new SourceCursor('abc');

        self::assertSame('', $cursor->takeWhile(static fn (string $c): bool => $c === 'z'));
        self::assertSame(0, $cursor->offset());
    }

    public function testTakeUntilConsumesTheTerminatorWithoutReturningIt(): void
    {
        $cursor = new SourceCursor('body %} rest');

        self::assertSame('body ', $cursor->takeUntil('%}'));
        self::assertSame(' rest', $cursor->takeRest());
    }

    public function testTakeUntilAnAbsentTerminatorConsumesTheRest(): void
    {
        $cursor = new SourceCursor('body without a close');

        self::assertSame('body without a close', $cursor->takeUntil('%}'));
        self::assertTrue($cursor->atEnd());
    }

    public function testTakeQuotedDropsTheQuotesAndUnescapes(): void
    {
        $cursor = new SourceCursor('"a\\"b" tail');

        self::assertSame('a"b', $cursor->takeQuoted('"'));
        self::assertSame(' tail', $cursor->takeRest());
    }

    public function testTakeQuotedConsumesTheRestWhenTheRunIsNeverClosed(): void
    {
        $cursor = new SourceCursor("'abc");

        self::assertSame('abc', $cursor->takeQuoted("'"));
        self::assertTrue($cursor->atEnd());
    }

    public function testTakeQuotedEndsOnATrailingBackslash(): void
    {
        $cursor = new SourceCursor("'ab\\");

        self::assertSame('ab', $cursor->takeQuoted("'"));
        self::assertTrue($cursor->atEnd());
    }

    public function testTextBetweenReadsBackOverConsumedInput(): void
    {
        $cursor = new SourceCursor('abcdef');

        $cursor->advance(4);

        self::assertSame('bcd', $cursor->textBetween(1, 4));
    }

    public function testTextBetweenIsEmptyWhenTheOffsetsMeetOrCross(): void
    {
        $cursor = new SourceCursor('abcdef');

        self::assertSame('', $cursor->textBetween(4, 4));
        self::assertSame('', $cursor->textBetween(4, 1));
    }

    public function testTakeRestReturnsEverythingLeft(): void
    {
        $cursor = new SourceCursor('abc');

        $cursor->advance();

        self::assertSame('bc', $cursor->takeRest());
    }

    public function testTakeRestIsEmptyOnceTheSourceIsConsumed(): void
    {
        $cursor = new SourceCursor('abc');

        $cursor->takeRest();

        self::assertSame('', $cursor->takeRest());
    }
}
