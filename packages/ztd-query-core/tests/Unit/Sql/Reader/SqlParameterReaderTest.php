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
use ZtdQuery\Sql\Reader\SqlLexeme;
use ZtdQuery\Sql\Reader\SqlParameterReader;
use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlTokenKind;

#[CoversClass(SqlParameterReader::class)]
#[UsesClass(SqlLexeme::class)]
#[UsesClass(SqlLexerProfile::class)]
#[UsesClass(LexicalDelimiters::class)]
#[UsesClass(LexicalPattern::class)]
#[UsesClass(SqlCommentProfile::class)]
#[UsesClass(SqlParameterProfile::class)]
#[UsesClass(SqlQuoteProfile::class)]
#[UsesClass(SqlSymbolProfile::class)]
final class SqlParameterReaderTest extends TestCase
{
    public function testReadAtAnswersNothingWhereNoPlaceholderBegins(): void
    {
        self::assertNull((new SqlParameterReader())->readAt('a', 0, FakeSqlLexerProfiles::allCapabilities()));
    }

    public function testReadAtReadsAPlaceholderWrittenByPositionAsFarAsTheDialectSpellsIt(): void
    {
        $lexeme = (new SqlParameterReader())->readAt('$12,', 0, FakeSqlLexerProfiles::allCapabilities());

        self::assertSame([SqlTokenKind::Parameter, 3], [$lexeme?->kind, $lexeme?->end]);
    }

    public function testReadAtAnswersNothingForAPrefixThatNoNameFollows(): void
    {
        self::assertNull((new SqlParameterReader())->readAt(': ', 0, FakeSqlLexerProfiles::allCapabilities()));
    }

    public function testReadAtReadsAPlaceholderWrittenByName(): void
    {
        $lexeme = (new SqlParameterReader())->readAt(':name,', 0, FakeSqlLexerProfiles::allCapabilities());

        self::assertSame([SqlTokenKind::Parameter, 5], [$lexeme?->kind, $lexeme?->end]);
    }

    public function testReadAtReadsAPlaceholderNameSpelledInPartsAsOneLexeme(): void
    {
        $lexeme = (new SqlParameterReader())->readAt(':a::b,', 0, FakeSqlLexerProfiles::allCapabilities());

        self::assertSame([SqlTokenKind::Parameter, 5], [$lexeme?->kind, $lexeme?->end]);
    }

    public function testReadAtReadsTheSuffixTheDialectAllowsAPlaceholderToCarry(): void
    {
        $lexeme = (new SqlParameterReader())->readAt(':a(1),', 0, FakeSqlLexerProfiles::allCapabilities());

        self::assertSame([SqlTokenKind::Parameter, 5], [$lexeme?->kind, $lexeme?->end]);
    }

    public function testEndOfNameStopsAtASeparatorNoFurtherNameFollows(): void
    {
        $profile = FakeSqlLexerProfiles::allCapabilities();

        self::assertSame(2, (new SqlParameterReader())->endOfName(':a::', 1, ':', $profile));
    }

    public function testEndOfNameStopsAtTheEndOfTheStatement(): void
    {
        $profile = FakeSqlLexerProfiles::allCapabilities();

        self::assertSame(3, (new SqlParameterReader())->endOfName(':ab', 1, ':', $profile));
    }
}
