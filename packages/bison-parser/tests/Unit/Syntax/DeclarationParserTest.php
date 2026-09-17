<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use BisonParser\Ast\Declaration\Code;
use BisonParser\Ast\Declaration\CodeProps;
use BisonParser\Ast\Declaration\Define;
use BisonParser\Ast\Declaration\DefineForm;
use BisonParser\Ast\Declaration\Expect;
use BisonParser\Ast\Declaration\Flag;
use BisonParser\Ast\Declaration\InitialAction;
use BisonParser\Ast\Declaration\Option;
use BisonParser\Ast\Declaration\Param;
use BisonParser\Ast\Declaration\ParamKind;
use BisonParser\Ast\Declaration\Prologue;
use BisonParser\Ast\Declaration\Start;
use BisonParser\Ast\Declaration\Symbols\Alias;
use BisonParser\Ast\Declaration\Symbols\Associativity;
use BisonParser\Ast\Declaration\Symbols\PrecedenceDeclaration;
use BisonParser\Ast\Declaration\Symbols\SymbolClass;
use BisonParser\Ast\Declaration\Symbols\SymbolDeclaration;
use BisonParser\Ast\Declaration\Symbols\SymbolEntry;
use BisonParser\Ast\Declaration\UnionDeclaration;
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
use BisonParser\Syntax\DeclarationParser;
use BisonParser\Syntax\SymbolListParser;
use BisonParser\Syntax\TokenStream;
use BisonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeclarationParser::class)]
#[UsesClass(Alias::class)]
#[UsesClass(Associativity::class)]
#[UsesClass(Code::class)]
#[UsesClass(CodeProps::class)]
#[UsesClass(CodeReader::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Define::class)]
#[UsesClass(DefineForm::class)]
#[UsesClass(Directives::class)]
#[UsesClass(Escapes::class)]
#[UsesClass(Expect::class)]
#[UsesClass(Flag::class)]
#[UsesClass(InitialAction::class)]
#[UsesClass(Location::class)]
#[UsesClass(Option::class)]
#[UsesClass(Param::class)]
#[UsesClass(ParamKind::class)]
#[UsesClass(PrecedenceDeclaration::class)]
#[UsesClass(Prologue::class)]
#[UsesClass(Scanner::class)]
#[UsesClass(Start::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolClass::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(SymbolEntry::class)]
#[UsesClass(SymbolKind::class)]
#[UsesClass(SymbolListParser::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(Tag::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenStream::class)]
#[UsesClass(UnionDeclaration::class)]
#[Small]
final class DeclarationParserTest extends TestCase
{
    public function testStarts(): void
    {
        $parser = new DeclarationParser();

        self::assertTrue($parser->starts(new Token(TokenKind::Prologue, '', new Location(1, 1))));
        self::assertTrue($parser->starts(new Token(TokenKind::Directive, 'token', new Location(1, 1), '%token')));
        self::assertFalse($parser->starts(new Token(TokenKind::Directive, 'prec', new Location(1, 1), '%prec')));
        self::assertFalse($parser->starts(new Token(TokenKind::Directive, 'empty', new Location(1, 1), '%empty')));
        self::assertFalse($parser->starts(new Token(TokenKind::IdentifierColon, 'expr', new Location(1, 1))));
    }

    public function testParse(): void
    {
        $parser = new DeclarationParser();
        $prologue = $parser->parse(new TokenStream((new Scanner())->scan('%{ int x; %}')));
        $flag = $parser->parse(new TokenStream((new Scanner())->scan('%debug')));

        self::assertInstanceOf(Prologue::class, $prologue);
        self::assertSame(' int x; ', $prologue->code);
        self::assertInstanceOf(Flag::class, $flag);
    }

    public function testParseRejectsANonDeclaration(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a declaration but found 'expr' at 1:1");

        (new DeclarationParser())->parse(new TokenStream((new Scanner())->scan('expr: ;')));
    }

    public function testDirective(): void
    {
        $parser = new DeclarationParser();
        $stream = new TokenStream((new Scanner())->scan(implode("\n", [
            '%pure_parser %defines %require "3.8" %expect-rr 2 %initial-action { @$ = 1; } %lex-param {int a} {int b}',
            '%union YYSTYPE { int i; } %start program \'x\' %printer { p($$); } <*> NUM %code requires { #include "x.h" }',
            '%term <int> NUM 258 "number" %type <t> expr %binary \'+\' "-"',
        ])));
        $flag = $parser->directive($stream->next(), $stream);
        $header = $parser->directive($stream->next(), $stream);
        $require = $parser->directive($stream->next(), $stream);
        $expect = $parser->directive($stream->next(), $stream);
        $initial = $parser->directive($stream->next(), $stream);
        $param = $parser->directive($stream->next(), $stream);
        $union = $parser->directive($stream->next(), $stream);
        $start = $parser->directive($stream->next(), $stream);
        $printer = $parser->directive($stream->next(), $stream);
        $code = $parser->directive($stream->next(), $stream);
        $token = $parser->directive($stream->next(), $stream);
        $type = $parser->directive($stream->next(), $stream);
        $binary = $parser->directive($stream->next(), $stream);

        self::assertTrue($stream->is(TokenKind::End));
        self::assertInstanceOf(Flag::class, $flag);
        self::assertSame(['pure-parser', '%pure_parser'], [$flag->name, $flag->raw]);
        self::assertInstanceOf(Option::class, $header);
        self::assertSame(['header', null], [$header->name, $header->value]);
        self::assertInstanceOf(Option::class, $require);
        self::assertSame(['require', '3.8'], [$require->name, $require->value]);
        self::assertInstanceOf(Expect::class, $expect);
        self::assertSame([2, true], [$expect->count, $expect->reduceReduce]);
        self::assertInstanceOf(InitialAction::class, $initial);
        self::assertSame(' @$ = 1; ', $initial->code);
        self::assertInstanceOf(Param::class, $param);
        self::assertSame([ParamKind::Lex, ['int a', 'int b']], [$param->kind, $param->codes]);
        self::assertInstanceOf(UnionDeclaration::class, $union);
        self::assertSame(['YYSTYPE', ' int i; '], [$union->name, $union->code]);
        self::assertInstanceOf(Start::class, $start);
        self::assertSame(['program', 'x'], [$start->symbols[0]->value, $start->symbols[1]->value]);
        self::assertInstanceOf(CodeProps::class, $printer);
        self::assertSame([true, ' p($$); ', Tag::class, Symbol::class], [$printer->printer, $printer->code, $printer->targets[0]::class, $printer->targets[1]::class]);
        self::assertInstanceOf(Code::class, $code);
        self::assertSame(['requires', ' #include "x.h" '], [$code->qualifier, $code->code]);
        self::assertInstanceOf(SymbolDeclaration::class, $token);
        self::assertSame([SymbolClass::Token, 'NUM', 'int', 258, 'number'], [$token->class, $token->entries[0]->symbol->value, $token->entries[0]->tag, $token->entries[0]->number, $token->entries[0]->alias?->text]);
        self::assertInstanceOf(SymbolDeclaration::class, $type);
        self::assertSame([SymbolClass::Type, 'expr', 't'], [$type->class, $type->entries[0]->symbol->value, $type->entries[0]->tag]);
        self::assertInstanceOf(PrecedenceDeclaration::class, $binary);
        self::assertSame([Associativity::NonAssoc, '+', '-'], [$binary->associativity, $binary->entries[0]->symbol->value, $binary->entries[1]->symbol->value]);
    }

    public function testDirectiveRejectsAnOptionWithoutItsString(): void
    {
        $stream = new TokenStream((new Scanner())->scan('%require 3'));

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Expected a string after %require but found integer at 1:10');

        (new DeclarationParser())->directive($stream->next(), $stream);
    }

    public function testDirectiveRejectsARuleModifier(): void
    {
        $stream = new TokenStream([new Token(TokenKind::Directive, 'prec', new Location(1, 1), '%prec'), new Token(TokenKind::End, '', new Location(1, 6))]);

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a declaration but found '%prec' at 1:1");

        (new DeclarationParser())->directive($stream->next(), $stream);
    }

    public function testDefine(): void
    {
        $parser = new DeclarationParser();
        $keyword = $parser->define(new TokenStream((new Scanner())->scan('api.pure full')), new Location(1, 1));
        $string = $parser->define(new TokenStream((new Scanner())->scan('api.prefix = "yy"')), new Location(2, 1));
        $code = $parser->define(new TokenStream((new Scanner())->scan('api.value.type {struct s}')), new Location(3, 1));
        $bare = $parser->define(new TokenStream((new Scanner())->scan('parse.trace %%')), new Location(4, 1));

        self::assertSame(['api.pure', 'full', DefineForm::Keyword], [$keyword->variable, $keyword->value, $keyword->form]);
        self::assertSame(['api.prefix', 'yy', DefineForm::String], [$string->variable, $string->value, $string->form]);
        self::assertSame(['api.value.type', 'struct s', DefineForm::Code], [$code->variable, $code->value, $code->form]);
        self::assertSame(['parse.trace', null, null, '4:1'], [$bare->variable, $bare->value, $bare->form, (string) $bare->location()]);
    }

    public function testDefineRejectsAMissingVariable(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a variable after %define but found '%%' at 1:1");

        (new DeclarationParser())->define(new TokenStream((new Scanner())->scan('%%')), new Location(1, 1));
    }

    public function testCodes(): void
    {
        $stream = new TokenStream((new Scanner())->scan('{int a} {int b} %%'));

        self::assertSame(['int a', 'int b'], (new DeclarationParser())->codes($stream, 'param'));
        self::assertTrue($stream->is(TokenKind::Section));
    }

    public function testCodesRejectsAMissingBlock(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected braced code after %param but found '%%' at 1:1");

        (new DeclarationParser())->codes(new TokenStream((new Scanner())->scan('%%')), 'param');
    }
}
