<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlLexerProfiles;
use ZtdQuery\Sql\SqlIdentifierComponent;
use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenScanner;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(SqlIdentifierComponent::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenStream::class)]
#[UsesClass(SqlTokenScanner::class)]
#[UsesClass(SqlLexerProfile::class)]
#[UsesClass(\ZtdQuery\Sql\LexicalDelimiters::class)]
#[UsesClass(\ZtdQuery\Sql\LexicalPattern::class)]
final class SqlIdentifierComponentTest extends TestCase
{
    public function testAtReadsABareNameAndSaysWhereItLeftOff(): void
    {
        $tokens = SqlTokenStream::tokenize('users.id', FakeSqlLexerProfiles::standard())->significantTokens();

        self::assertSame(['users', 1], (new SqlIdentifierComponent())->at($tokens, 0, FakeSqlLexerProfiles::standard()));
    }

    public function testAtReadsAQuotedNameWithoutTheQuotesAroundIt(): void
    {
        $tokens = SqlTokenStream::tokenize('"order"', FakeSqlLexerProfiles::standard())->significantTokens();

        self::assertSame(['order', 1], (new SqlIdentifierComponent())->at($tokens, 0, FakeSqlLexerProfiles::standard()));
    }

    public function testAtIsNothingWhereNoNameIsWritten(): void
    {
        $tokens = SqlTokenStream::tokenize('1', FakeSqlLexerProfiles::standard())->significantTokens();

        self::assertNull((new SqlIdentifierComponent())->at($tokens, 0, FakeSqlLexerProfiles::standard()));
    }

    public function testAtIsNothingPastTheEndOfWhatWasWritten(): void
    {
        $tokens = SqlTokenStream::tokenize('users', FakeSqlLexerProfiles::standard())->significantTokens();

        self::assertNull((new SqlIdentifierComponent())->at($tokens, 5, FakeSqlLexerProfiles::standard()));
    }
}
