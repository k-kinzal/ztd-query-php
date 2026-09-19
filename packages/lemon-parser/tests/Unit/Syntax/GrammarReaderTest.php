<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use LemonParser\Ast\CodeBlock;
use LemonParser\Ast\Declaration\Associativity;
use LemonParser\Ast\Declaration\PrecedenceDeclaration;
use LemonParser\Ast\GrammarFile;
use LemonParser\Ast\Location;
use LemonParser\Ast\RhsItem;
use LemonParser\Ast\Rule;
use LemonParser\Ast\Symbol;
use LemonParser\Scanner\CodeReader;
use LemonParser\Scanner\Cursor;
use LemonParser\Scanner\Scanner;
use LemonParser\Scanner\Token;
use LemonParser\Scanner\TokenKind;
use LemonParser\Syntax\DeclarationReader;
use LemonParser\Syntax\GrammarReader;
use LemonParser\Syntax\RuleReader;
use LemonParser\Syntax\SymbolListReader;
use LemonParser\Syntax\SymbolRegistry;
use LemonParser\Syntax\TokenStream;
use LemonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrammarReader::class)]
#[UsesClass(CodeReader::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Location::class)]
#[UsesClass(Scanner::class)]
#[UsesClass(SymbolRegistry::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenStream::class)]
#[UsesClass(Associativity::class)]
#[UsesClass(CodeBlock::class)]
#[UsesClass(DeclarationReader::class)]
#[UsesClass(GrammarFile::class)]
#[UsesClass(PrecedenceDeclaration::class)]
#[UsesClass(RhsItem::class)]
#[UsesClass(Rule::class)]
#[UsesClass(RuleReader::class)]
#[UsesClass(SymbolListReader::class)]
#[UsesClass(Symbol::class)]
#[Small]
final class GrammarReaderTest extends TestCase
{
    public function testRead(): void
    {
        $file = (new GrammarReader())->read(new TokenStream((new Scanner())->scan("%left PLUS.\nexpr ::= expr PLUS expr. [PLUS] { add(); }\nexpr ::= NUM. %right STAR. {NEVER-REDUCE} { num(); }\n")));

        self::assertSame([PrecedenceDeclaration::class, Rule::class, Rule::class, PrecedenceDeclaration::class], array_map(static fn (object $item): string => $item::class, $file->items));
        $rules = $file->rules();
        self::assertSame(['PLUS', ' add(); ', false], [$rules[0]->precedence?->name, $rules[0]->code?->code, $rules[0]->neverReduce]);
        self::assertSame([null, ' num(); ', true], [$rules[1]->precedence?->name, $rules[1]->code?->code, $rules[1]->neverReduce]);
    }

    public function testReadRejectsAStrayToken(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Token "PLUS" should be either "%" or a nonterminal name. at 1:1');

        (new GrammarReader())->read(new TokenStream((new Scanner())->scan('PLUS ::= A.')));
    }

    public function testCode(): void
    {
        $reader = new GrammarReader();
        $rule = new Rule(new Symbol('a', new Location(1, 1)), null, [], null, null, false, new Location(1, 1));

        $withCode = $reader->code($rule, new Token(TokenKind::Code, ' x ', new Location(1, 10), '{ x }'));
        $marked = $reader->code($rule, new Token(TokenKind::Code, 'NEVER-REDUCE', new Location(1, 10), '{NEVER-REDUCE}'));

        self::assertSame([' x ', false], [$withCode->code?->code, $withCode->neverReduce]);
        self::assertSame([null, true], [$marked->code, $marked->neverReduce]);
    }

    public function testCodeRejectsAMissingRule(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('There is no prior rule upon which to attach the code fragment which begins on this line. at 1:10');

        (new GrammarReader())->code(null, new Token(TokenKind::Code, ' x ', new Location(1, 10), '{ x }'));
    }

    public function testCodeRejectsASecondBlock(): void
    {
        $rule = new Rule(new Symbol('a', new Location(1, 1)), null, [], null, new CodeBlock(' x ', new Location(1, 5)), false, new Location(1, 1));

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Code fragment beginning on this line is not the first to follow the previous rule. at 1:10');

        (new GrammarReader())->code($rule, new Token(TokenKind::Code, ' y ', new Location(1, 10), '{ y }'));
    }

    public function testPrecedence(): void
    {
        $rule = new Rule(new Symbol('a', new Location(1, 1)), null, [], null, null, false, new Location(1, 1));
        $stream = new TokenStream((new Scanner())->scan('PLUS] rest'));
        $registry = new SymbolRegistry();

        $marked = (new GrammarReader())->precedence($rule, $stream, $registry);

        self::assertSame('PLUS', $marked->precedence?->name);
        self::assertTrue($registry->isKnown('PLUS'));
        self::assertSame('rest', $stream->peek()->text);
    }

    public function testPrecedenceRejectsANonterminal(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('The precedence symbol must be a terminal. at 1:1');

        (new GrammarReader())->precedence(null, new TokenStream((new Scanner())->scan('plus]')), new SymbolRegistry());
    }

    public function testPrecedenceRejectsAMissingRule(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('There is no prior rule to assign precedence "[PLUS]". at 1:1');

        (new GrammarReader())->precedence(null, new TokenStream((new Scanner())->scan('PLUS]')), new SymbolRegistry());
    }

    public function testPrecedenceRejectsASecondMark(): void
    {
        $rule = new Rule(new Symbol('a', new Location(1, 1)), null, [], new Symbol('STAR', new Location(1, 5)), null, false, new Location(1, 1));

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Precedence mark on this line is not the first to follow the previous rule. at 1:1');

        (new GrammarReader())->precedence($rule, new TokenStream((new Scanner())->scan('PLUS]')), new SymbolRegistry());
    }

    public function testPrecedenceRejectsAMissingCloser(): void
    {
        $rule = new Rule(new Symbol('a', new Location(1, 1)), null, [], null, null, false, new Location(1, 1));

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Missing "]" on precedence mark. at 1:6');

        (new GrammarReader())->precedence($rule, new TokenStream((new Scanner())->scan('PLUS .')), new SymbolRegistry());
    }
}
