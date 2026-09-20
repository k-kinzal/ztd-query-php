<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use BisonParser\Ast\Declaration\Symbols\Alias;
use BisonParser\Ast\Declaration\Symbols\SymbolEntry;
use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use BisonParser\Ast\Tag;
use BisonParser\Scanner\CodeReader;
use BisonParser\Scanner\Cursor;
use BisonParser\Scanner\Directives;
use BisonParser\Scanner\Escapes;
use BisonParser\Scanner\Scanner;
use BisonParser\Scanner\Token;
use BisonParser\Scanner\TokenKind;
use BisonParser\Syntax\SymbolListParser;
use BisonParser\Syntax\TokenStream;
use BisonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SymbolListParser::class)]
#[UsesClass(Alias::class)]
#[UsesClass(CodeReader::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Directives::class)]
#[UsesClass(Escapes::class)]
#[UsesClass(Location::class)]
#[UsesClass(Scanner::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolEntry::class)]
#[UsesClass(SymbolKind::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(Tag::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenStream::class)]
#[Small]
final class SymbolListParserTest extends TestCase
{
    public function testTokenDeclarations(): void
    {
        $entries = (new SymbolListParser())->tokenDeclarations(new TokenStream((new Scanner())->scan('<int> NUM 258 "number" STR _("text") <str> ID \'+\' ;')));

        self::assertSame(
            [['NUM', 'int', 258, 'number', false], ['STR', 'int', null, 'text', true], ['ID', 'str', null, null, null], ['+', 'str', null, null, null]],
            array_map(static fn (SymbolEntry $entry): array => [$entry->symbol->value, $entry->tag, $entry->number, $entry->alias?->text, $entry->alias?->translatable], $entries),
        );
    }

    public function testPrecedenceDeclarations(): void
    {
        $entries = (new SymbolListParser())->precedenceDeclarations(new TokenStream((new Scanner())->scan('<int> PLUS 258 "+" \'-\' %%')));

        self::assertSame(
            [['PLUS', 'int', 258, SymbolKind::Identifier], ['+', 'int', null, SymbolKind::String], ['-', 'int', null, SymbolKind::CharLiteral]],
            array_map(static fn (SymbolEntry $entry): array => [$entry->symbol->value, $entry->tag, $entry->number, $entry->symbol->kind], $entries),
        );
    }

    public function testTypeDeclarations(): void
    {
        $stream = new TokenStream((new Scanner())->scan('<int> expr term 258'));

        $entries = (new SymbolListParser())->typeDeclarations($stream);

        self::assertSame([['expr', 'int', null], ['term', 'int', null]], array_map(static fn (SymbolEntry $entry): array => [$entry->symbol->value, $entry->tag, $entry->number], $entries));
        self::assertTrue($stream->is(TokenKind::Integer));
    }

    public function testEntries(): void
    {
        $entries = (new SymbolListParser())->entries(new TokenStream((new Scanner())->scan('A B <t> C %%')), false, false, [SymbolKind::Identifier]);

        self::assertSame([['A', null], ['B', null], ['C', 't']], array_map(static fn (SymbolEntry $entry): array => [$entry->symbol->value, $entry->tag], $entries));
    }

    public function testEntriesRejectsAnEmptyList(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a symbol but found '%%' at 1:1");

        (new SymbolListParser())->entries(new TokenStream((new Scanner())->scan('%%')), true, true, [SymbolKind::Identifier]);
    }

    public function testEntriesRejectsATagWithoutASymbol(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a symbol after the tag but found '%%' at 1:7");

        (new SymbolListParser())->entries(new TokenStream((new Scanner())->scan('<int> %%')), true, true, [SymbolKind::Identifier]);
    }

    public function testEntry(): void
    {
        $parser = new SymbolListParser();
        $numbered = $parser->entry(new TokenStream((new Scanner())->scan('NUM 258 "n"')), 'int', true, false);
        $string = $parser->entry(new TokenStream((new Scanner())->scan('"n" 258')), null, true, true);

        self::assertSame(['NUM', 'int', 258, null], [$numbered->symbol->value, $numbered->tag, $numbered->number, $numbered->alias]);
        self::assertSame('_("text")', $parser->entry(new TokenStream((new Scanner())->scan('STR _("text")')), null, true, true)->alias?->spelling);
        self::assertSame(['n', null, 258, null], [$string->symbol->value, $string->tag, $string->number, $string->alias]);
    }

    public function testSymbols(): void
    {
        $symbols = (new SymbolListParser())->symbols(new TokenStream((new Scanner())->scan('program \'x\' "y" %%')));

        self::assertSame(['program', 'x', 'y'], array_map(static fn (Symbol $symbol): string => $symbol->value, $symbols));
    }

    public function testSymbolsRejectsAnEmptyList(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Expected a symbol but found end of file at 1:1');

        (new SymbolListParser())->symbols(new TokenStream((new Scanner())->scan('')));
    }

    public function testTargets(): void
    {
        $targets = (new SymbolListParser())->targets(new TokenStream((new Scanner())->scan('NUM <int> <*> <> \'+\' %%')));

        self::assertSame(
            [[Symbol::class, 'NUM'], [Tag::class, 'int'], [Tag::class, '*'], [Tag::class, ''], [Symbol::class, '+']],
            array_map(static fn (Symbol|Tag $target): array => [$target::class, $target instanceof Tag ? $target->name : $target->value], $targets),
        );
    }

    public function testTargetsRejectsAnEmptyList(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a symbol or tag but found ';' at 1:1");

        (new SymbolListParser())->targets(new TokenStream((new Scanner())->scan(';')));
    }

    public function testPassesLine(): void
    {
        $parser = new SymbolListParser();
        $between = new TokenStream((new Scanner())->scan("A\n#line 2\nB"));
        $before = new TokenStream((new Scanner())->scan("#line 2\n%%"));
        $between->next();

        self::assertTrue($parser->passesLine($between));
        self::assertSame('B', $between->peek()->text);
        self::assertFalse($parser->passesLine($before));
        self::assertTrue($before->is(TokenKind::Line));
    }

    public function testEntriesPassOverALineBetweenEntries(): void
    {
        $stream = new TokenStream((new Scanner())->scan("A\n#line 2\n<t> B\n#line 3\n%%"));

        $entries = (new SymbolListParser())->entries($stream, false, false, [SymbolKind::Identifier]);

        self::assertSame([['A', null], ['B', 't']], array_map(static fn (SymbolEntry $entry): array => [$entry->symbol->value, $entry->tag], $entries));
        self::assertTrue($stream->is(TokenKind::Line));
    }

    public function testStartsSymbol(): void
    {
        $parser = new SymbolListParser();

        self::assertTrue($parser->startsSymbol(new Token(TokenKind::Identifier, 'x', new Location(1, 1))));
        self::assertTrue($parser->startsSymbol(new Token(TokenKind::CharLiteral, 'x', new Location(1, 1))));
        self::assertTrue($parser->startsSymbol(new Token(TokenKind::String, 'x', new Location(1, 1))));
        self::assertFalse($parser->startsSymbol(new Token(TokenKind::IdentifierColon, 'x', new Location(1, 1))));
        self::assertFalse($parser->startsSymbol(new Token(TokenKind::Tag, 'x', new Location(1, 1))));
    }

    public function testSymbol(): void
    {
        $parser = new SymbolListParser();
        $lhs = $parser->symbol(new Token(TokenKind::IdentifierColon, 'expr', new Location(3, 1)));

        self::assertSame([SymbolKind::Identifier, 'expr', '3:1'], [$lhs->kind, $lhs->value, (string) $lhs->location]);
        $literal = $parser->symbol(new Token(TokenKind::CharLiteral, '+', new Location(1, 1), "'+'"));
        $string = $parser->symbol(new Token(TokenKind::String, 'x', new Location(1, 1), '"\\x78"'));

        self::assertSame([SymbolKind::CharLiteral, "'+'"], [$literal->kind, $literal->spelling]);
        self::assertSame([SymbolKind::String, '"\\x78"'], [$string->kind, $string->spelling]);
        self::assertNull($lhs->spelling);
    }

    public function testSymbolRejectsAnotherToken(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Expected a symbol but found braced code at 1:1');

        (new SymbolListParser())->symbol(new Token(TokenKind::Code, 'x', new Location(1, 1)));
    }

    public function testNtermDeclarations(): void
    {
        $stream = new TokenStream((new Scanner())->scan('<int> expr <str> name term %%'));

        $entries = (new SymbolListParser())->ntermDeclarations($stream);

        self::assertSame([['expr', 'int'], ['name', 'str'], ['term', 'str']], array_map(static fn (SymbolEntry $entry): array => [$entry->symbol->value, $entry->tag], $entries));
        self::assertTrue($stream->is(TokenKind::Section));
    }

    public function testNtermDeclarationsLeaveANumberUnread(): void
    {
        $stream = new TokenStream((new Scanner())->scan('expr 258'));

        self::assertCount(1, (new SymbolListParser())->ntermDeclarations($stream));
        self::assertTrue($stream->is(TokenKind::Integer));
    }

    public function testNtermDeclarationsRejectAStringAlias(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Expected an identifier but found string at 1:6');

        (new SymbolListParser())->ntermDeclarations(new TokenStream((new Scanner())->scan('expr "e"')));
    }

    public function testNtermDeclarationsRejectACharacterLiteral(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Expected an identifier but found character literal at 1:1');

        (new SymbolListParser())->ntermDeclarations(new TokenStream((new Scanner())->scan("'c'")));
    }

    public function testTokenDeclarationsRejectAStringStartingAnEntry(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Expected an identifier or a character literal but found string at 1:7');

        (new SymbolListParser())->tokenDeclarations(new TokenStream((new Scanner())->scan('X "a" "b"')));
    }

    public function testEntriesRejectASymbolOfAnotherKind(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Expected an identifier or a character literal but found string at 1:3');

        (new SymbolListParser())->entries(new TokenStream((new Scanner())->scan('a "b"')), false, false, [SymbolKind::Identifier, SymbolKind::CharLiteral]);
    }

    public function testKind(): void
    {
        $parser = new SymbolListParser();

        self::assertSame(SymbolKind::Identifier, $parser->kind(new Token(TokenKind::Identifier, 'x', new Location(1, 1))));
        self::assertSame(SymbolKind::Identifier, $parser->kind(new Token(TokenKind::IdentifierColon, 'x', new Location(1, 1))));
        self::assertSame(SymbolKind::CharLiteral, $parser->kind(new Token(TokenKind::CharLiteral, 'x', new Location(1, 1))));
        self::assertSame(SymbolKind::String, $parser->kind(new Token(TokenKind::String, 'x', new Location(1, 1))));
        self::assertNull($parser->kind(new Token(TokenKind::Tag, 'x', new Location(1, 1))));
    }

    public function testDescribe(): void
    {
        $parser = new SymbolListParser();

        self::assertSame('an identifier', $parser->describe([SymbolKind::Identifier]));
        self::assertSame('an identifier or a character literal', $parser->describe([SymbolKind::Identifier, SymbolKind::CharLiteral]));
        self::assertSame('an identifier, a character literal or a string', $parser->describe([SymbolKind::Identifier, SymbolKind::CharLiteral, SymbolKind::String]));
    }
}
