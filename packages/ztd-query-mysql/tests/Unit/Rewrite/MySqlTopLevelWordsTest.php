<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Rewrite\MySqlTopLevelWords;

#[CoversClass(MySqlTopLevelWords::class)]
final class MySqlTopLevelWordsTest extends TestCase
{
    public function testAfterBodyAnswersNothingBeforeAnyBodyIsWritten(): void
    {
        self::assertSame([], (new MySqlTopLevelWords())->afterBody('SELECT id FROM users'));
    }

    public function testAfterBodyAnswersTheWordsWrittenAfterTheBody(): void
    {
        $words = (new MySqlTopLevelWords())->afterBody('WITH c AS (SELECT 1) DELETE FROM c');

        self::assertSame(['DELETE', 'FROM', 'C'], $words);
    }

    public function testAfterBodyLeavesTheWordsWrittenInsideTheBody(): void
    {
        $words = (new MySqlTopLevelWords())->afterBody('WITH c AS (SELECT 1 FROM t) SELECT 1');

        self::assertSame(['SELECT'], $words);
    }

    public function testAfterBodyLeavesWhatIsWrittenInsideQuotes(): void
    {
        $words = (new MySqlTopLevelWords())->afterBody("WITH c AS (SELECT 1) SELECT 'DELETE'");

        self::assertSame(['SELECT'], $words);
    }

    public function testAfterBodyLeavesWhatIsWrittenInsideBackticks(): void
    {
        $words = (new MySqlTopLevelWords())->afterBody('WITH c AS (SELECT 1) SELECT `DELETE`');

        self::assertSame(['SELECT'], $words);
    }

    public function testClosesQuoteSaysABacktickIsNeverEscaped(): void
    {
        $words = new MySqlTopLevelWords();

        self::assertSame([true, true], [$words->closesQuote('`a`', 2, '`'), $words->closesQuote('\\`', 1, '`')]);
    }

    public function testClosesQuoteSaysAQuoteAfterABackslashStandsForItself(): void
    {
        $words = new MySqlTopLevelWords();

        self::assertSame([false, true], [$words->closesQuote("a\\'", 2, "'"), $words->closesQuote("a'", 1, "'")]);
    }

    public function testClosesQuoteSaysAnyOtherCharacterCloosesNothing(): void
    {
        self::assertFalse((new MySqlTopLevelWords())->closesQuote("'a", 1, "'"));
    }
}
