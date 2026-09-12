<?php

declare(strict_types=1);

namespace Tests\Unit\Sql\Reader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlLexerProfiles;
use ZtdQuery\Sql\LexicalDelimiters;
use ZtdQuery\Sql\LexicalPattern;
use ZtdQuery\Sql\Profile\SqlCommentProfile;
use ZtdQuery\Sql\Profile\SqlParameterProfile;
use ZtdQuery\Sql\Profile\SqlQuoteProfile;
use ZtdQuery\Sql\Profile\SqlSymbolProfile;
use ZtdQuery\Sql\Reader\SqlDelimitedReader;
use ZtdQuery\Sql\Reader\SqlLexeme;
use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlTokenKind;

#[CoversClass(SqlDelimitedReader::class)]
#[UsesClass(SqlLexeme::class)]
#[UsesClass(SqlLexerProfile::class)]
#[UsesClass(LexicalDelimiters::class)]
#[UsesClass(LexicalPattern::class)]
#[UsesClass(SqlCommentProfile::class)]
#[UsesClass(SqlParameterProfile::class)]
#[UsesClass(SqlQuoteProfile::class)]
#[UsesClass(SqlSymbolProfile::class)]
final class SqlDelimitedReaderTest extends TestCase
{
    public function testReadAtAnswersNothingWhereNoDelimiterOpens(): void
    {
        self::assertNull((new SqlDelimitedReader())->readAt('SELECT', 0, FakeSqlLexerProfiles::standard()));
    }

    public function testReadAtReadsAQuotedRunOfTextAsAString(): void
    {
        $lexeme = (new SqlDelimitedReader())->readAt("'a b' x", 0, FakeSqlLexerProfiles::standard());

        self::assertSame([SqlTokenKind::String, 5], [$lexeme?->kind, $lexeme?->end]);
    }

    public function testReadAtReadsAQuotedNameAsAnIdentifierRatherThanAString(): void
    {
        $lexeme = (new SqlDelimitedReader())->readAt('"a b" x', 0, FakeSqlLexerProfiles::standard());

        self::assertSame([SqlTokenKind::QuotedIdentifier, 5], [$lexeme?->kind, $lexeme?->end]);
    }

    public function testReadAtReadsADollarQuotedRunUpToTheTagThatOpenedIt(): void
    {
        $lexeme = (new SqlDelimitedReader())->readAt('$t$a b$t$x', 0, FakeSqlLexerProfiles::allCapabilities());

        self::assertSame([SqlTokenKind::String, 9], [$lexeme?->kind, $lexeme?->end]);
    }

    public function testReadAtReadsADollarQuotedRunToTheEndOfAStatementThatNeverCloses(): void
    {
        $lexeme = (new SqlDelimitedReader())->readAt('$t$a b', 0, FakeSqlLexerProfiles::allCapabilities());

        self::assertSame([SqlTokenKind::String, 6], [$lexeme?->kind, $lexeme?->end]);
    }

    public function testEndOfDelimitedAnswersWhereTheClosingDelimiterLeftOff(): void
    {
        self::assertSame(3, (new SqlDelimitedReader())->endOfDelimited("'a'x", 0, "'", "'", false));
    }

    public function testEndOfDelimitedReadsPastADoubledDelimiterBecauseItWritesTheDelimiter(): void
    {
        self::assertSame(5, (new SqlDelimitedReader())->endOfDelimited("'a'''", 0, "'", "'", false));
    }

    public function testEndOfDelimitedReadsPastAnEscapedDelimiterWhereBackslashesEscape(): void
    {
        self::assertSame(5, (new SqlDelimitedReader())->endOfDelimited("'a\\''", 0, "'", "'", true));
    }

    public function testEndOfDelimitedStopsAtTheEndOfAStatementThatNeverClosedTheRun(): void
    {
        self::assertSame(2, (new SqlDelimitedReader())->endOfDelimited("'a", 0, "'", "'", false));
    }
}
