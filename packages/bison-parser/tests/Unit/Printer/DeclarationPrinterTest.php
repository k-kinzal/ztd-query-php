<?php

declare(strict_types=1);

namespace Tests\Unit\Printer;

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
use BisonParser\Ast\Line;
use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use BisonParser\Ast\Tag;
use BisonParser\Printer\DeclarationPrinter;
use BisonParser\Printer\SymbolPrinter;
use BisonParser\Scanner\Escapes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeclarationPrinter::class)]
#[UsesClass(Alias::class)]
#[UsesClass(Associativity::class)]
#[UsesClass(Code::class)]
#[UsesClass(CodeProps::class)]
#[UsesClass(Define::class)]
#[UsesClass(DefineForm::class)]
#[UsesClass(Escapes::class)]
#[UsesClass(Expect::class)]
#[UsesClass(Flag::class)]
#[UsesClass(InitialAction::class)]
#[UsesClass(Line::class)]
#[UsesClass(Location::class)]
#[UsesClass(Option::class)]
#[UsesClass(Param::class)]
#[UsesClass(ParamKind::class)]
#[UsesClass(PrecedenceDeclaration::class)]
#[UsesClass(Prologue::class)]
#[UsesClass(Start::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolClass::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(SymbolEntry::class)]
#[UsesClass(SymbolKind::class)]
#[UsesClass(SymbolPrinter::class)]
#[UsesClass(Tag::class)]
#[UsesClass(UnionDeclaration::class)]
#[Small]
final class DeclarationPrinterTest extends TestCase
{
    public function testPrint(): void
    {
        $printer = new DeclarationPrinter();
        $at = new Location(1, 1);
        $num = new Symbol(SymbolKind::Identifier, 'NUM', $at);

        self::assertSame('%{ int x; %}', $printer->print(new Prologue(' int x; ', $at)));
        self::assertSame('%pure_parser', $printer->print(new Flag('pure-parser', '%pure_parser', $at)));
        self::assertSame('%defines', $printer->print(new Option('header', '%defines', null, $at)));
        self::assertSame('%require "3.8"', $printer->print(new Option('require', '%require', '3.8', $at)));
        self::assertSame('%define api.pure full', $printer->print(new Define('api.pure', 'full', DefineForm::Keyword, $at)));
        self::assertSame('%expect 2', $printer->print(new Expect(2, false, $at)));
        self::assertSame('%expect-rr 1', $printer->print(new Expect(1, true, $at)));
        self::assertSame('%initial-action { @$ = 1; }', $printer->print(new InitialAction(' @$ = 1; ', $at)));
        self::assertSame('%lex-param {int a} {int b}', $printer->print(new Param(ParamKind::Lex, ['int a', 'int b'], $at)));
        self::assertSame('%union { int i; }', $printer->print(new UnionDeclaration(null, ' int i; ', $at)));
        self::assertSame('%union YYSTYPE { int i; }', $printer->print(new UnionDeclaration('YYSTYPE', ' int i; ', $at)));
        self::assertSame('%start program', $printer->print(new Start([new Symbol(SymbolKind::Identifier, 'program', $at)], $at)));
        self::assertSame('%printer { p($$); } <*> NUM', $printer->print(new CodeProps(true, ' p($$); ', [new Tag(Tag::ANY, $at), $num], $at)));
        self::assertSame('%destructor { free($$); } <>', $printer->print(new CodeProps(false, ' free($$); ', [new Tag(Tag::NONE, $at)], $at)));
        self::assertSame('%code { x }', $printer->print(new Code(null, ' x ', $at)));
        self::assertSame('%code requires { x }', $printer->print(new Code('requires', ' x ', $at)));
        self::assertSame('%token <int> NUM 258 "number"', $printer->print(new SymbolDeclaration(SymbolClass::Token, [new SymbolEntry($num, 'int', 258, new Alias('number', false, $at))], $at)));
        self::assertSame("%left '+' '-'", $printer->print(new PrecedenceDeclaration(Associativity::Left, [new SymbolEntry(new Symbol(SymbolKind::CharLiteral, '+', $at), null, null, null), new SymbolEntry(new Symbol(SymbolKind::CharLiteral, '-', $at), null, null, null)], $at)));
    }

    public function testOption(): void
    {
        $printer = new DeclarationPrinter();
        $at = new Location(1, 1);

        self::assertSame('%defines', $printer->option(new Option('header', '%defines', null, $at)));
        self::assertSame('%require "3.8"', $printer->option(new Option('require', '%require', '3.8', $at)));
    }

    public function testExpect(): void
    {
        $printer = new DeclarationPrinter();

        self::assertSame('%expect 2', $printer->expect(2, false));
        self::assertSame('%expect-rr 1', $printer->expect(1, true));
    }

    public function testUnion(): void
    {
        $printer = new DeclarationPrinter();
        $at = new Location(1, 1);

        self::assertSame('%union { int i; }', $printer->union(new UnionDeclaration(null, ' int i; ', $at)));
        self::assertSame('%union YYSTYPE { int i; }', $printer->union(new UnionDeclaration('YYSTYPE', ' int i; ', $at)));
    }

    public function testProps(): void
    {
        $printer = new DeclarationPrinter();
        $at = new Location(1, 1);

        self::assertSame('%printer { p($$); } <*>', $printer->props(new CodeProps(true, ' p($$); ', [new Tag(Tag::ANY, $at)], $at)));
        self::assertSame('%destructor { free($$); } NUM', $printer->props(new CodeProps(false, ' free($$); ', [new Symbol(SymbolKind::Identifier, 'NUM', $at)], $at)));
    }

    public function testCode(): void
    {
        $printer = new DeclarationPrinter();
        $at = new Location(1, 1);

        self::assertSame('%code { x }', $printer->code(new Code(null, ' x ', $at)));
        self::assertSame('%code requires { x }', $printer->code(new Code('requires', ' x ', $at)));
    }

    public function testLine(): void
    {
        $printer = new DeclarationPrinter();
        $at = new Location(1, 1);

        self::assertSame('#line 12 "dir/file.y"', $printer->line(new Line(12, 'dir/file.y', $at)));
        self::assertSame('#line 3', $printer->line(new Line(3, null, $at)));
        self::assertSame('#line 3', $printer->print(new Line(3, null, $at)));
    }

    public function testDefine(): void
    {
        $printer = new DeclarationPrinter();
        $at = new Location(1, 1);

        self::assertSame('%define api.pure full', $printer->define(new Define('api.pure', 'full', DefineForm::Keyword, $at)));
        self::assertSame('%define api.prefix "yy"', $printer->define(new Define('api.prefix', 'yy', DefineForm::String, $at)));
        self::assertSame('%define api.value.type {struct s}', $printer->define(new Define('api.value.type', 'struct s', DefineForm::Code, $at)));
        self::assertSame('%define parse.trace', $printer->define(new Define('parse.trace', null, null, $at)));
    }

    public function testTargets(): void
    {
        $at = new Location(1, 1);

        self::assertSame("NUM <int> <*> '+'", (new DeclarationPrinter())->targets([new Symbol(SymbolKind::Identifier, 'NUM', $at), new Tag('int', $at), new Tag(Tag::ANY, $at), new Symbol(SymbolKind::CharLiteral, '+', $at)]));
    }

    public function testEntries(): void
    {
        $at = new Location(1, 1);
        $entries = [
            new SymbolEntry(new Symbol(SymbolKind::Identifier, 'A', $at), null, null, null),
            new SymbolEntry(new Symbol(SymbolKind::Identifier, 'B', $at), 'int', 300, new Alias('bee', true, $at)),
            new SymbolEntry(new Symbol(SymbolKind::Identifier, 'C', $at), 'int', null, new Alias('cee', false, $at)),
            new SymbolEntry(new Symbol(SymbolKind::String, 'dee', $at), 'str', null, null),
            new SymbolEntry(new Symbol(SymbolKind::Identifier, 'E', $at), 'str', null, new Alias('e', false, $at, '"\\x65"')),
        ];

        self::assertSame(' A <int> B 300 _("bee") C "cee" <str> "dee" E "\\x65"', (new DeclarationPrinter())->entries($entries));
    }
}
