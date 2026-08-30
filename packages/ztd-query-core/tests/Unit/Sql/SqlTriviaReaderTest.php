<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlLexerProfiles;
use ZtdQuery\Sql\LexicalDelimiters;
use ZtdQuery\Sql\LexicalPattern;
use ZtdQuery\Sql\SqlBlockCommentReader;
use ZtdQuery\Sql\SqlLexeme;
use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTriviaReader;

#[CoversClass(SqlTriviaReader::class)]
#[UsesClass(SqlBlockCommentReader::class)]
#[UsesClass(SqlLexeme::class)]
#[UsesClass(SqlLexerProfile::class)]
#[UsesClass(LexicalDelimiters::class)]
#[UsesClass(LexicalPattern::class)]
final class SqlTriviaReaderTest extends TestCase
{
    public function testReadAtAnswersNothingWhereSomethingMeaningfulBegins(): void
    {
        self::assertNull((new SqlTriviaReader())->readAt('SELECT', 0, FakeSqlLexerProfiles::standard()));
    }

    public function testReadAtReadsAWholeRunOfWhitespaceAsOneLexeme(): void
    {
        $lexeme = (new SqlTriviaReader())->readAt("  \n\tx", 0, FakeSqlLexerProfiles::standard());

        self::assertSame([SqlTokenKind::Whitespace, 4], [$lexeme?->kind, $lexeme?->end]);
    }

    public function testReadAtLeavesTheNewlineThatEndsALineCommentToBeReadAsWhitespace(): void
    {
        $lexeme = (new SqlTriviaReader())->readAt("-- a\nx", 0, FakeSqlLexerProfiles::standard());

        self::assertSame([SqlTokenKind::Comment, 4], [$lexeme?->kind, $lexeme?->end]);
    }

    public function testReadAtReadsALineCommentToTheEndOfAStatementThatNeverBreaksTheLine(): void
    {
        $lexeme = (new SqlTriviaReader())->readAt('-- a', 0, FakeSqlLexerProfiles::standard());

        self::assertSame([SqlTokenKind::Comment, 4], [$lexeme?->kind, $lexeme?->end]);
    }

    public function testReadAtReadsAWholeBlockCommentAsOneLexeme(): void
    {
        $lexeme = (new SqlTriviaReader())->readAt('/* a */x', 0, FakeSqlLexerProfiles::standard());

        self::assertSame([SqlTokenKind::Comment, 7], [$lexeme?->kind, $lexeme?->end]);
    }
}
