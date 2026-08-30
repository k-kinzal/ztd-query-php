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
use ZtdQuery\Sql\SqlLexerProfile;

#[CoversClass(SqlBlockCommentReader::class)]
#[UsesClass(SqlLexerProfile::class)]
#[UsesClass(LexicalDelimiters::class)]
#[UsesClass(LexicalPattern::class)]
final class SqlBlockCommentReaderTest extends TestCase
{
    public function testEndAtAnswersNothingWhereNoCommentBegins(): void
    {
        self::assertNull((new SqlBlockCommentReader())->endAt('SELECT 1', 0, FakeSqlLexerProfiles::standard()));
    }

    public function testEndAtAnswersJustPastTheClosingDelimiter(): void
    {
        self::assertSame(9, (new SqlBlockCommentReader())->endAt('/* a b */1', 0, FakeSqlLexerProfiles::standard()));
    }

    public function testEndAtClosesAtTheFirstDelimiterWhereACommentHoldsNoOther(): void
    {
        $profile = FakeSqlLexerProfiles::custom(nestedBlockComments: false);

        self::assertSame(12, (new SqlBlockCommentReader())->endAt('/* a /* b */ */', 0, $profile));
    }

    public function testEndAtReadsTheWholeOfANestedCommentWhereOneMayHoldAnother(): void
    {
        self::assertSame(15, (new SqlBlockCommentReader())->endAt('/* a /* b */ */', 0, FakeSqlLexerProfiles::standard()));
    }

    public function testEndAtStopsAtTheEndOfAStatementThatNeverClosedTheComment(): void
    {
        self::assertSame(4, (new SqlBlockCommentReader())->endAt('/* a', 0, FakeSqlLexerProfiles::standard()));
    }
}
