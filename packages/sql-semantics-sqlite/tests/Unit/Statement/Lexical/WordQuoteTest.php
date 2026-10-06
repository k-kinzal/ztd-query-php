<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote;

#[CoversClass(WordQuote::class)]
#[Small]
final class WordQuoteTest extends TestCase
{
    public function testCasesNameEveryDelimiterSqliteReads(): void
    {
        self::assertSame(['Bare', 'Single', 'Double', 'Backtick', 'Bracket'], array_column(WordQuote::cases(), 'name'));
    }
}
