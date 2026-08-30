<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlLexerProfiles;
use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenScanner;

#[CoversClass(SqlTokenScanner::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(\ZtdQuery\Sql\SqlLexeme::class)]
#[UsesClass(\ZtdQuery\Sql\SqlTriviaReader::class)]
#[UsesClass(\ZtdQuery\Sql\SqlBlockCommentReader::class)]
#[UsesClass(\ZtdQuery\Sql\SqlDelimitedReader::class)]
#[UsesClass(\ZtdQuery\Sql\SqlParameterReader::class)]
#[UsesClass(\ZtdQuery\Sql\SqlWordReader::class)]
#[UsesClass(SqlLexerProfile::class)]
#[UsesClass(\ZtdQuery\Sql\LexicalDelimiters::class)]
#[UsesClass(\ZtdQuery\Sql\LexicalPattern::class)]
final class SqlTokenScannerTest extends TestCase
{
    public function testScanLosesNothingOfTheStatementItRead(): void
    {
        $sql = "SELECT /* c */ \"a\", 'b' -- tail\nFROM t";

        $tokens = (new SqlTokenScanner())->scan($sql, FakeSqlLexerProfiles::standard());

        self::assertSame($sql, implode('', array_map(static fn (SqlToken $t): string => $t->text, $tokens)));
    }

    public function testScanReadsEachLexemeAsWhatItIs(): void
    {
        $tokens = (new SqlTokenScanner())->scan("SELECT 1, 'a'", FakeSqlLexerProfiles::standard());

        self::assertSame(
            [
                SqlTokenKind::Word,
                SqlTokenKind::Whitespace,
                SqlTokenKind::Number,
                SqlTokenKind::Symbol,
                SqlTokenKind::Whitespace,
                SqlTokenKind::String,
            ],
            array_map(static fn (SqlToken $t): SqlTokenKind => $t->kind, $tokens),
        );
    }

    public function testScanCountsHowDeeplyEachLexemeIsNested(): void
    {
        $tokens = (new SqlTokenScanner())->scan('((1))', FakeSqlLexerProfiles::standard());

        self::assertSame([0, 1, 2, 1, 0], array_map(static fn (SqlToken $t): int => $t->depth, $tokens));
    }

    public function testScanReadsAnUnterminatedStringToTheEndOfTheStatement(): void
    {
        $tokens = (new SqlTokenScanner())->scan("SELECT 'a", FakeSqlLexerProfiles::standard());

        self::assertSame("'a", $tokens[count($tokens) - 1]->text);
    }

    public function testScanReadsAWholeCommentAsOneLexeme(): void
    {
        $tokens = (new SqlTokenScanner())->scan('/* a b */1', FakeSqlLexerProfiles::standard());

        self::assertSame('/* a b */', $tokens[0]->text);
    }




}
