<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlLexerProfiles;
use ZtdQuery\Sql\Profile\SqlCommentProfile;
use ZtdQuery\Sql\Profile\SqlParameterProfile;
use ZtdQuery\Sql\Profile\SqlQuoteProfile;
use ZtdQuery\Sql\Profile\SqlSymbolProfile;
use ZtdQuery\Sql\Reader\SqlBlockCommentReader;
use ZtdQuery\Sql\Reader\SqlDelimitedReader;
use ZtdQuery\Sql\Reader\SqlLexeme;
use ZtdQuery\Sql\Reader\SqlParameterReader;
use ZtdQuery\Sql\Reader\SqlTriviaReader;
use ZtdQuery\Sql\Reader\SqlWordReader;
use ZtdQuery\Sql\SqlKeywordSequence;
use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenScanner;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(SqlKeywordSequence::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenStream::class)]
#[UsesClass(SqlTokenScanner::class)]
#[UsesClass(SqlLexerProfile::class)]
#[UsesClass(\ZtdQuery\Sql\LexicalDelimiters::class)]
#[UsesClass(\ZtdQuery\Sql\LexicalPattern::class)]
#[UsesClass(SqlCommentProfile::class)]
#[UsesClass(SqlParameterProfile::class)]
#[UsesClass(SqlQuoteProfile::class)]
#[UsesClass(SqlSymbolProfile::class)]
#[UsesClass(SqlBlockCommentReader::class)]
#[UsesClass(SqlDelimitedReader::class)]
#[UsesClass(SqlLexeme::class)]
#[UsesClass(SqlParameterReader::class)]
#[UsesClass(SqlTriviaReader::class)]
#[UsesClass(SqlWordReader::class)]
final class SqlKeywordSequenceTest extends TestCase
{
    public function testPositionInAnswersWhereTheRunOfKeywordsBegins(): void
    {
        $tokens = SqlTokenStream::tokenize(
            'SELECT a FROM t ORDER BY a',
            FakeSqlLexerProfiles::standard(),
        )->significantTokens();

        self::assertSame(4, (new SqlKeywordSequence())->positionIn($tokens, ['ORDER', 'BY'], 0));
    }

    public function testPositionInIgnoresTheSameWordsWrittenInsideParentheses(): void
    {
        $tokens = SqlTokenStream::tokenize(
            'SELECT (SELECT a FROM t ORDER BY a) FROM u',
            FakeSqlLexerProfiles::standard(),
        )->significantTokens();

        self::assertNull((new SqlKeywordSequence())->positionIn($tokens, ['ORDER', 'BY'], 0));
    }

    public function testPositionInIsNothingWhereTheRunIsNotWrittenAtAll(): void
    {
        $tokens = SqlTokenStream::tokenize('SELECT a FROM t', FakeSqlLexerProfiles::standard())
            ->significantTokens();

        self::assertNull((new SqlKeywordSequence())->positionIn($tokens, ['GROUP', 'BY'], 0));
    }

    public function testPositionInLooksNoEarlierThanItWasToldTo(): void
    {
        $tokens = SqlTokenStream::tokenize(
            'SELECT a FROM t WHERE a = 1',
            FakeSqlLexerProfiles::standard(),
        )->significantTokens();

        self::assertNull((new SqlKeywordSequence())->positionIn($tokens, ['SELECT'], 1));
    }

    public function testPositionInIsNothingWhereOnlyPartOfTheRunIsWritten(): void
    {
        $tokens = SqlTokenStream::tokenize('SELECT a FROM t ORDER', FakeSqlLexerProfiles::standard())
            ->significantTokens();

        self::assertNull((new SqlKeywordSequence())->positionIn($tokens, ['ORDER', 'BY'], 0));
    }
}
