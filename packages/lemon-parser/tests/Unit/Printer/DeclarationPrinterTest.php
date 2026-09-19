<?php

declare(strict_types=1);

namespace Tests\Unit\Printer;

use LemonParser\Ast\Declaration\ArgumentForm;
use LemonParser\Ast\Declaration\Associativity;
use LemonParser\Ast\Declaration\Destructor;
use LemonParser\Ast\Declaration\Directive;
use LemonParser\Ast\Declaration\DirectiveKeyword;
use LemonParser\Ast\Declaration\Fallback;
use LemonParser\Ast\Declaration\PrecedenceDeclaration;
use LemonParser\Ast\Declaration\TokenClass;
use LemonParser\Ast\Declaration\TokenDeclaration;
use LemonParser\Ast\Declaration\TypeDeclaration;
use LemonParser\Ast\Declaration\Wildcard;
use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;
use LemonParser\Printer\DeclarationPrinter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeclarationPrinter::class)]
#[UsesClass(ArgumentForm::class)]
#[UsesClass(Associativity::class)]
#[UsesClass(Destructor::class)]
#[UsesClass(Directive::class)]
#[UsesClass(DirectiveKeyword::class)]
#[UsesClass(Fallback::class)]
#[UsesClass(Location::class)]
#[UsesClass(PrecedenceDeclaration::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(TokenClass::class)]
#[UsesClass(TokenDeclaration::class)]
#[UsesClass(TypeDeclaration::class)]
#[UsesClass(Wildcard::class)]
#[Small]
final class DeclarationPrinterTest extends TestCase
{
    public function testPrint(): void
    {
        $printer = new DeclarationPrinter();
        $at = new Location(1, 1);
        $id = new Symbol('ID', $at);
        $expr = new Symbol('expr', $at);

        self::assertSame('%name Calc', $printer->print(new Directive(DirectiveKeyword::Name, 'Calc', ArgumentForm::Word, $at)));
        self::assertSame('%include { int x; }', $printer->print(new Directive(DirectiveKeyword::Include, ' int x; ', ArgumentForm::Code, $at)));
        self::assertSame('%destructor expr { free($$); }', $printer->print(new Destructor($expr, ' free($$); ', ArgumentForm::Code, $at)));
        self::assertSame('%type expr "Expr*"', $printer->print(new TypeDeclaration($expr, 'Expr*', ArgumentForm::String, $at)));
        self::assertSame('%left PLUS MINUS.', $printer->print(new PrecedenceDeclaration(Associativity::Left, [new Symbol('PLUS', $at), new Symbol('MINUS', $at)], $at)));
        self::assertSame('%fallback ID ABORT.', $printer->print(new Fallback([$id, new Symbol('ABORT', $at)], $at)));
        self::assertSame('%token ID.', $printer->print(new TokenDeclaration([$id], $at)));
        self::assertSame('%wildcard ANY.', $printer->print(new Wildcard(new Symbol('ANY', $at), $at)));
        self::assertSame('%wildcard.', $printer->print(new Wildcard(null, $at)));
        self::assertSame('%token_class id ID|INDEXED.', $printer->print(new TokenClass(new Symbol('id', $at), [$id, new Symbol('INDEXED', $at)], $at)));
    }

    public function testArgument(): void
    {
        $printer = new DeclarationPrinter();

        self::assertSame('{ x }', $printer->argument(' x ', ArgumentForm::Code));
        self::assertSame('"x"', $printer->argument('x', ArgumentForm::String));
        self::assertSame('x', $printer->argument('x', ArgumentForm::Word));
    }

    public function testList(): void
    {
        $at = new Location(1, 1);
        $printer = new DeclarationPrinter();

        self::assertSame('.', $printer->list([]));
        self::assertSame(' A B.', $printer->list([new Symbol('A', $at), new Symbol('B', $at)]));
    }
}
