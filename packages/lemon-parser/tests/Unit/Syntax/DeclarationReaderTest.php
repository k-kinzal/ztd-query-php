<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

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
use LemonParser\Scanner\CodeReader;
use LemonParser\Scanner\Cursor;
use LemonParser\Scanner\Scanner;
use LemonParser\Scanner\Token;
use LemonParser\Scanner\TokenKind;
use LemonParser\Syntax\DeclarationReader;
use LemonParser\Syntax\SymbolListReader;
use LemonParser\Syntax\SymbolRegistry;
use LemonParser\Syntax\TokenStream;
use LemonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeclarationReader::class)]
#[UsesClass(CodeReader::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Location::class)]
#[UsesClass(Scanner::class)]
#[UsesClass(SymbolRegistry::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenStream::class)]
#[UsesClass(ArgumentForm::class)]
#[UsesClass(Associativity::class)]
#[UsesClass(Destructor::class)]
#[UsesClass(Directive::class)]
#[UsesClass(DirectiveKeyword::class)]
#[UsesClass(Fallback::class)]
#[UsesClass(PrecedenceDeclaration::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolListReader::class)]
#[UsesClass(TokenClass::class)]
#[UsesClass(TokenDeclaration::class)]
#[UsesClass(TypeDeclaration::class)]
#[UsesClass(Wildcard::class)]
#[Small]
final class DeclarationReaderTest extends TestCase
{
    public function testRead(): void
    {
        $reader = new DeclarationReader();
        $registry = new SymbolRegistry();
        $stream = new TokenStream((new Scanner())->scan('name Calc left PLUS MINUS. destructor expr { free($$); } type expr {Expr*} fallback ID ABORT. token SEMI. wildcard ANY. token_class id ID|INDEXED.'));
        $at = new Location(1, 1);
        $name = $reader->read($stream, $registry, $at);
        $left = $reader->read($stream, $registry, $at);
        $destructor = $reader->read($stream, $registry, $at);
        $type = $reader->read($stream, $registry, $at);
        $fallback = $reader->read($stream, $registry, $at);
        $token = $reader->read($stream, $registry, $at);
        $wildcard = $reader->read($stream, $registry, $at);
        $class = $reader->read($stream, $registry, $at);

        self::assertTrue($stream->eof());
        self::assertInstanceOf(Directive::class, $name);
        self::assertSame([DirectiveKeyword::Name, 'Calc', ArgumentForm::Word], [$name->keyword, $name->value, $name->form]);
        self::assertInstanceOf(PrecedenceDeclaration::class, $left);
        self::assertSame([Associativity::Left, 'PLUS', 'MINUS'], [$left->associativity, $left->symbols[0]->name, $left->symbols[1]->name]);
        self::assertInstanceOf(Destructor::class, $destructor);
        self::assertSame(['expr', ' free($$); ', ArgumentForm::Code], [$destructor->symbol->name, $destructor->value, $destructor->form]);
        self::assertInstanceOf(TypeDeclaration::class, $type);
        self::assertSame(['expr', 'Expr*'], [$type->symbol->name, $type->value]);
        self::assertInstanceOf(Fallback::class, $fallback);
        self::assertSame(['ID', 'ABORT'], [$fallback->fallback()?->name, $fallback->tokens()[0]->name]);
        self::assertInstanceOf(TokenDeclaration::class, $token);
        self::assertSame('SEMI', $token->symbols[0]->name);
        self::assertInstanceOf(Wildcard::class, $wildcard);
        self::assertSame('ANY', $wildcard->symbol?->name);
        self::assertInstanceOf(TokenClass::class, $class);
        self::assertSame(['id', 'ID', 'INDEXED'], [$class->name->name, $class->tokens[0]->name, $class->tokens[1]->name]);
    }

    public function testReadRejectsAKeywordThatIsNotAWord(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Illegal declaration keyword: "1". at 1:1');

        (new DeclarationReader())->read(new TokenStream((new Scanner())->scan('1')), new SymbolRegistry(), new Location(1, 1));
    }

    public function testReadRejectsAnUnknownKeyword(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unknown declaration keyword: "%tokens". at 1:1');

        (new DeclarationReader())->read(new TokenStream((new Scanner())->scan('tokens A.')), new SymbolRegistry(), new Location(1, 1));
    }

    public function testArgument(): void
    {
        $reader = new DeclarationReader();
        $keyword = new Token(TokenKind::Word, 'name', new Location(1, 1), 'name');

        self::assertSame([' x ', ArgumentForm::Code], $reader->argument(new TokenStream((new Scanner())->scan('{ x }')), $keyword));
        self::assertSame(['x y', ArgumentForm::String], $reader->argument(new TokenStream((new Scanner())->scan('"x y"')), $keyword));
        self::assertSame(['100', ArgumentForm::Word], $reader->argument(new TokenStream((new Scanner())->scan('100')), $keyword));
    }

    public function testArgumentRejectsPunctuation(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Illegal argument to %name: . at 1:1');

        (new DeclarationReader())->argument(new TokenStream((new Scanner())->scan('.')), new Token(TokenKind::Word, 'name', new Location(1, 1), 'name'));
    }

    public function testReadDestructor(): void
    {
        $registry = new SymbolRegistry();

        $destructor = (new DeclarationReader())->readDestructor(new TokenStream((new Scanner())->scan('expr "free"')), $registry, new Location(2, 1));

        self::assertSame(['expr', 'free', ArgumentForm::String, '2:1'], [$destructor->symbol->name, $destructor->value, $destructor->form, (string) $destructor->location()]);
        self::assertTrue($registry->isKnown('expr'));
    }

    public function testReadDestructorRejectsAMissingSymbol(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Symbol name missing after %destructor keyword at 1:1');

        (new DeclarationReader())->readDestructor(new TokenStream((new Scanner())->scan('{ x }')), new SymbolRegistry(), new Location(1, 1));
    }

    public function testReadType(): void
    {
        $type = (new DeclarationReader())->readType(new TokenStream((new Scanner())->scan('expr {Expr*}')), new SymbolRegistry(), new Location(2, 1));

        self::assertSame(['expr', 'Expr*', ArgumentForm::Code], [$type->symbol->name, $type->value, $type->form]);
    }

    public function testReadTypeRejectsAMissingSymbol(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Symbol name missing after %type keyword at 1:1');

        (new DeclarationReader())->readType(new TokenStream((new Scanner())->scan('{ x }')), new SymbolRegistry(), new Location(1, 1));
    }

    public function testReadTypeRejectsASecondType(): void
    {
        $registry = new SymbolRegistry();
        $reader = new DeclarationReader();
        $reader->readType(new TokenStream((new Scanner())->scan('expr {Expr*}')), $registry, new Location(1, 1));

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Symbol %type "expr" already defined at 1:1');

        $reader->readType(new TokenStream((new Scanner())->scan('expr {Expr*}')), $registry, new Location(2, 1));
    }

    public function testReadTokenClass(): void
    {
        $registry = new SymbolRegistry();

        $class = (new DeclarationReader())->readTokenClass(new TokenStream((new Scanner())->scan('id ID|INDEXED.')), $registry, new Location(2, 1));

        self::assertSame(['id', 'ID', 'INDEXED'], [$class->name->name, $class->tokens[0]->name, $class->tokens[1]->name]);
        self::assertTrue($registry->isKnown('id'));
    }

    public function testReadTokenClassRejectsATerminalName(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%token_class must be followed by an identifier: ID at 1:1');

        (new DeclarationReader())->readTokenClass(new TokenStream((new Scanner())->scan('ID A.')), new SymbolRegistry(), new Location(1, 1));
    }

    public function testReadTokenClassRejectsAKnownName(): void
    {
        $registry = new SymbolRegistry();
        $registry->see('id');

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Symbol "id" already used at 1:1');

        (new DeclarationReader())->readTokenClass(new TokenStream((new Scanner())->scan('id ID.')), $registry, new Location(1, 1));
    }
}
