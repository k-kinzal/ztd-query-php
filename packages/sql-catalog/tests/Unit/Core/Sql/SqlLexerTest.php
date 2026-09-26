<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Sql\SqlLexer;
use SqlCatalog\Core\Sql\SqlToken;
use SqlCatalog\Core\Sql\SqlTokenKind;

#[CoversClass(SqlLexer::class)]
#[UsesClass(SqlToken::class)]
final class SqlLexerTest extends TestCase
{
    public function testTokenizeSeparatesWordsAndSymbols(): void
    {
        $tokens = (new SqlLexer())->tokenize('SELECT a, b');
        self::assertSame(['SELECT', 'a', ',', 'b'], array_map(
            static fn (SqlToken $token): string => $token->text,
            $tokens,
        ));
    }

    public function testTokenizeTracksParenthesisDepth(): void
    {
        $tokens = (new SqlLexer())->tokenize('SELECT (1)');
        self::assertSame([0, 1, 1, 0], array_map(
            static fn (SqlToken $token): int => $token->depth,
            $tokens,
        ));
    }

    public function testTokenizeNeverLosesGroundOnUnbalancedParentheses(): void
    {
        $tokens = (new SqlLexer())->tokenize('SELECT 1)');
        self::assertSame(0, $tokens[count($tokens) - 1]->depth);
    }

    public function testTokenizeKeepsStringBodiesTogether(): void
    {
        $tokens = (new SqlLexer())->tokenize("SELECT 'a, b' FROM t");
        self::assertSame("'a, b'", $tokens[1]->text);
        self::assertSame(SqlTokenKind::Text, $tokens[1]->kind);
    }

    #[DataProvider('providerReadToken')]
    public function testReadToken(string $sql, SqlTokenKind $kind, string $text): void
    {
        $token = (new SqlLexer())->readToken($sql, 0, 0);
        self::assertSame($kind, $token->kind);
        self::assertSame($text, $token->text);
    }

    /**
     * @return list<array{string, SqlTokenKind, string}>
     */
    public static function providerReadToken(): array
    {
        return [
            ['-- a comment', SqlTokenKind::Comment, '-- a comment'],
            ['users', SqlTokenKind::Word, 'users'],
            ['12.5', SqlTokenKind::Number, '12.5'],
            ['"quoted"', SqlTokenKind::Identifier, '"quoted"'],
            ['?', SqlTokenKind::Parameter, '?'],
            [':name', SqlTokenKind::Parameter, ':name'],
            ['::text', SqlTokenKind::Symbol, '::'],
            ['+', SqlTokenKind::Symbol, '+'],
        ];
    }

    public function testReadCommentRecognisesEveryCommentSyntax(): void
    {
        $lexer = new SqlLexer();
        self::assertSame('# a', $lexer->readComment('# a', 0));
        self::assertSame('/* a */', $lexer->readComment('/* a */ SELECT', 0));
        self::assertNull($lexer->readComment('SELECT', 0));
    }

    public function testReadQuotedHandlesEscapesAndDoubling(): void
    {
        $lexer = new SqlLexer();
        self::assertSame("'it''s'", $lexer->readQuoted("'it''s' x", 0));
        self::assertSame("'a\\'b'", $lexer->readQuoted("'a\\'b' x", 0));
        self::assertSame('`t`', $lexer->readQuoted('`t`', 0));
        self::assertNull($lexer->readQuoted('abc', 0));
    }

    public function testReadQuotedRunsToTheEndWhenTheQuoteIsNeverClosed(): void
    {
        self::assertSame("'abc", (new SqlLexer())->readQuoted("'abc", 0));
    }

    public function testReadParameterRecognisesEveryParameterSyntax(): void
    {
        $lexer = new SqlLexer();
        self::assertSame('?', $lexer->readParameter('?', 0));
        self::assertSame('?1', $lexer->readParameter('?1', 0));
        self::assertSame(':id', $lexer->readParameter(':id', 0));
        self::assertSame('$2', $lexer->readParameter('$2', 0));
        self::assertNull($lexer->readParameter('a', 0));
    }
}
