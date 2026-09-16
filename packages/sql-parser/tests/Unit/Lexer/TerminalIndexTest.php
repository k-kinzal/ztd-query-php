<?php

declare(strict_types=1);

namespace Tests\Unit\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Grammar\SymbolTable;
use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;
use SqlParser\Lexer\SourcePosition;
use SqlParser\Lexer\TerminalIndex;
use SqlParser\Lexer\Token;

#[CoversClass(TerminalIndex::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(SourcePosition::class)]
#[UsesClass(SymbolTable::class)]
#[UsesClass(Token::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
final class TerminalIndexTest extends TestCase
{
    public function testTokens(): void
    {
        $index = new TerminalIndex(new SymbolTable(['$end', 'SELECT', 'NUM'], ['$accept', 'stmt']));
        $tokens = $index->tokens([new Lexeme('SELECT', 'SELECT', 0), new Lexeme('NUM', '1', 7)], 'SELECT 1');

        self::assertSame([1, 2, 0], array_map(static fn (Token $token): int => $token->symbol, $tokens));
        self::assertSame('', $tokens[2]->text);
        self::assertSame(8, $tokens[2]->offset);
    }

    public function testToken(): void
    {
        $index = new TerminalIndex(new SymbolTable(['$end', 'NUM'], ['$accept', 'stmt']));
        $token = $index->token(new Lexeme('NUM', '42', 3), 'xx 42');

        self::assertSame(1, $token->symbol);
        self::assertSame('42', $token->text);
    }

    public function testTokenRejectsAnUnknownTerminal(): void
    {
        $this->expectException(LexicalException::class);

        (new TerminalIndex(new SymbolTable(['$end'], ['$accept'])))->token(new Lexeme('NUM', '1', 0), '1');
    }

    public function testTokenRejectsANonterminalName(): void
    {
        $this->expectException(LexicalException::class);

        (new TerminalIndex(new SymbolTable(['$end'], ['$accept', 'stmt'])))->token(new Lexeme('stmt', '', 0), '');
    }
}
