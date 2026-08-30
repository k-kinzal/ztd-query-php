<?php

declare(strict_types=1);

namespace Tests\Unit\Dialect;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlPdoPlaceholderEscaper;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlPdoRuns;

#[CoversClass(PgSqlPdoRuns::class)]
#[UsesClass(PgSqlPdoPlaceholderEscaper::class)]
final class PgSqlPdoRunsTest extends TestCase
{
    public function testTriviaEndTakesOneByteOfWhitespaceAtATime(): void
    {
        self::assertSame(1, (new PgSqlPdoRuns())->triviaEnd('  x', 0));
    }

    public function testTriviaEndLeavesTheNewlineThatEndsALineComment(): void
    {
        self::assertSame(4, (new PgSqlPdoRuns())->triviaEnd("-- a\nx", 0));
    }

    public function testTriviaEndReadsTheWholeOfANestedBlockComment(): void
    {
        self::assertSame(15, (new PgSqlPdoRuns())->triviaEnd('/* a /* b */ */x', 0));
    }

    public function testTriviaEndStopsAtTheEndOfAStatementThatNeverClosedTheComment(): void
    {
        self::assertSame(4, (new PgSqlPdoRuns())->triviaEnd('/* a', 0));
    }

    public function testTriviaEndAnswersNothingWhereNoTriviaStarts(): void
    {
        self::assertNull((new PgSqlPdoRuns())->triviaEnd('SELECT', 0));
    }

    public function testValueEndReadsAQuotedRunToItsClosingQuote(): void
    {
        self::assertSame(5, (new PgSqlPdoRuns())->valueEnd("'a b' x", 0));
    }

    public function testValueEndReadsADollarQuotedRunToItsClosingTag(): void
    {
        self::assertSame(9, (new PgSqlPdoRuns())->valueEnd('$t$a b$t$x', 0));
    }

    public function testValueEndReadsADollarQuotedRunToTheEndWhereItNeverCloses(): void
    {
        self::assertSame(6, (new PgSqlPdoRuns())->valueEnd('$t$a b', 0));
    }

    public function testValueEndAnswersNothingForADollarThatOpensNoRun(): void
    {
        self::assertNull((new PgSqlPdoRuns())->valueEnd('$ x', 0));
    }

    public function testValueEndAnswersNothingWhereNoRunStarts(): void
    {
        self::assertNull((new PgSqlPdoRuns())->valueEnd('SELECT', 0));
    }

    public function testQuotedEndReadsPastADoubledQuoteBecauseItWritesTheQuote(): void
    {
        self::assertSame(5, (new PgSqlPdoRuns())->quotedEnd("'a'''", 0, "'"));
    }

    public function testQuotedEndReadsPastAnEscapedQuoteInsideAnEscapeString(): void
    {
        self::assertSame(6, (new PgSqlPdoRuns())->quotedEnd("E'a\\''", 1, "'"));
    }

    public function testWordEndReadsAWholeBareWord(): void
    {
        self::assertSame(6, (new PgSqlPdoRuns())->wordEnd('SELECT 1', 0));
    }

    public function testWordEndAnswersNothingWhereNoWordStarts(): void
    {
        self::assertNull((new PgSqlPdoRuns())->wordEnd('1', 0));
    }

    public function testNumberEndReadsAWholeNumber(): void
    {
        self::assertSame(3, (new PgSqlPdoRuns())->numberEnd('1.5 x', 0));
    }

    public function testNumberEndAnswersNothingWhereNoNumberStarts(): void
    {
        self::assertNull((new PgSqlPdoRuns())->numberEnd('a', 0));
    }
}
