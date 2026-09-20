<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;
use LemonParser\Scanner\CodeReader;
use LemonParser\Scanner\Cursor;
use LemonParser\Scanner\Scanner;
use LemonParser\Scanner\Token;
use LemonParser\Scanner\TokenKind;
use LemonParser\Syntax\SymbolListReader;
use LemonParser\Syntax\SymbolRegistry;
use LemonParser\Syntax\TokenStream;
use LemonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SymbolListReader::class)]
#[UsesClass(CodeReader::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Location::class)]
#[UsesClass(Scanner::class)]
#[UsesClass(SymbolRegistry::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenStream::class)]
#[UsesClass(Symbol::class)]
#[Small]
final class SymbolListReaderTest extends TestCase
{
    public function testRanked(): void
    {
        $registry = new SymbolRegistry();

        $symbols = (new SymbolListReader())->ranked(new TokenStream((new Scanner())->scan('PLUS MINUS. x')), $registry);

        self::assertSame(['PLUS', 'MINUS'], array_map(static fn (Symbol $symbol): string => $symbol->name, $symbols));
        self::assertTrue($registry->isKnown('MINUS'));
    }

    public function testRankedRejectsANonterminal(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Can\'t assign a precedence to "expr". at 1:6');

        (new SymbolListReader())->ranked(new TokenStream((new Scanner())->scan('PLUS expr.')), new SymbolRegistry());
    }

    public function testFallbacks(): void
    {
        $registry = new SymbolRegistry();

        $symbols = (new SymbolListReader())->fallbacks(new TokenStream((new Scanner())->scan('ID ABORT AFTER.')), $registry);

        self::assertSame(['ID', 'ABORT', 'AFTER'], array_map(static fn (Symbol $symbol): string => $symbol->name, $symbols));
        self::assertTrue($registry->isKnown('ID'));
    }

    public function testFallbacksRejectsANonterminal(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%fallback argument "id" should be a token at 1:1');

        (new SymbolListReader())->fallbacks(new TokenStream((new Scanner())->scan('id ABORT.')), new SymbolRegistry());
    }

    public function testFallbacksRejectsASecondFallbackForAToken(): void
    {
        $registry = new SymbolRegistry();
        $reader = new SymbolListReader();
        $reader->fallbacks(new TokenStream((new Scanner())->scan('ID ABORT.')), $registry);

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('More than one fallback assigned to token ABORT at 1:7');

        $reader->fallbacks(new TokenStream((new Scanner())->scan('OTHER ABORT.')), $registry);
    }

    public function testTokens(): void
    {
        $registry = new SymbolRegistry();

        $symbols = (new SymbolListReader())->tokens(new TokenStream((new Scanner())->scan('SEMI LP.')), $registry, 'token');

        self::assertSame(['SEMI', 'LP'], array_map(static fn (Symbol $symbol): string => $symbol->name, $symbols));
        self::assertTrue($registry->isKnown('LP'));
    }

    public function testTokensRejectsANonterminal(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%token argument "semi" should be a token at 1:1');

        (new SymbolListReader())->tokens(new TokenStream((new Scanner())->scan('semi.')), new SymbolRegistry(), 'token');
    }

    public function testWildcard(): void
    {
        $reader = new SymbolListReader();

        self::assertSame('ANY', $reader->wildcard(new TokenStream((new Scanner())->scan('ANY.')), new SymbolRegistry())?->name);
        self::assertNull($reader->wildcard(new TokenStream((new Scanner())->scan('.')), new SymbolRegistry()));
    }

    public function testWildcardRejectsANonterminal(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%wildcard argument "any" should be a token at 1:1');

        (new SymbolListReader())->wildcard(new TokenStream((new Scanner())->scan('any.')), new SymbolRegistry());
    }

    public function testWildcardRejectsASecondToken(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Extra wildcard to token: OTHER at 1:5');

        (new SymbolListReader())->wildcard(new TokenStream((new Scanner())->scan('ANY OTHER.')), new SymbolRegistry());
    }

    public function testClassTokens(): void
    {
        $registry = new SymbolRegistry();

        $symbols = (new SymbolListReader())->classTokens(new TokenStream((new Scanner())->scan('ID|INDEXED /JOIN_KW KEY.')), $registry);

        self::assertSame(['ID', 'INDEXED', 'JOIN_KW', 'KEY'], array_map(static fn (Symbol $symbol): string => $symbol->name, $symbols));
        self::assertTrue($registry->isKnown('JOIN_KW'));
    }

    public function testClassTokensRejectsANonterminal(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%token_class argument "|indexed" should be a token at 1:3');

        (new SymbolListReader())->classTokens(new TokenStream((new Scanner())->scan('ID|indexed.')), new SymbolRegistry());
    }

    public function testUntilPeriod(): void
    {
        $stream = new TokenStream((new Scanner())->scan('A |B . C'));

        $taken = (new SymbolListReader())->untilPeriod($stream);

        self::assertSame(['A', 'B'], array_map(static fn (Token $token): string => $token->text, $taken));
        self::assertSame('C', $stream->peek()->text);
    }

    public function testUntilPeriodRejectsTheEndOfTheFile(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Declaration is not terminated by "." before the end of the file. at 1:4');

        (new SymbolListReader())->untilPeriod(new TokenStream((new Scanner())->scan('A B')));
    }
}
