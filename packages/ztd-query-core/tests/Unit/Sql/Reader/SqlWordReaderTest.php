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
use ZtdQuery\Sql\Reader\SqlWordReader;
use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlTokenKind;

#[CoversClass(SqlWordReader::class)]
#[UsesClass(SqlLexeme::class)]
#[UsesClass(SqlLexerProfile::class)]
#[UsesClass(LexicalDelimiters::class)]
#[UsesClass(LexicalPattern::class)]
#[UsesClass(SqlCommentProfile::class)]
#[UsesClass(SqlParameterProfile::class)]
#[UsesClass(SqlQuoteProfile::class)]
#[UsesClass(SqlSymbolProfile::class)]
final class SqlWordReaderTest extends TestCase
{
    public function testReadAtAnswersNothingWhereNeitherAWordNorANumberBegins(): void
    {
        self::assertNull((new SqlWordReader())->readAt(',a', 0, FakeSqlLexerProfiles::standard()));
    }

    public function testReadAtReadsAWholeBareWordAsOneLexeme(): void
    {
        $lexeme = (new SqlWordReader())->readAt('select_1 ', 0, FakeSqlLexerProfiles::standard());

        self::assertSame([SqlTokenKind::Word, 8], [$lexeme?->kind, $lexeme?->end]);
    }

    public function testReadAtReadsAWordThatRunsToTheEndOfTheStatement(): void
    {
        $lexeme = (new SqlWordReader())->readAt('abc', 0, FakeSqlLexerProfiles::standard());

        self::assertSame([SqlTokenKind::Word, 3], [$lexeme?->kind, $lexeme?->end]);
    }

    public function testReadAtReadsAsFarIntoANumberAsTheDialectSpellsOne(): void
    {
        $lexeme = (new SqlWordReader())->readAt('1.5e3,', 0, FakeSqlLexerProfiles::standard());

        self::assertSame([SqlTokenKind::Number, 5], [$lexeme?->kind, $lexeme?->end]);
    }
}
