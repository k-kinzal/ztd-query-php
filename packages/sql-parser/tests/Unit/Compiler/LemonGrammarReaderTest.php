<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler;

use LemonParser\Ast\CodeBlock;
use LemonParser\Ast\Declaration\ArgumentForm;
use LemonParser\Ast\Declaration\Declaration;
use LemonParser\Ast\Declaration\Destructor;
use LemonParser\Ast\Declaration\Directive;
use LemonParser\Ast\Declaration\DirectiveKeyword;
use LemonParser\Ast\Declaration\Fallback;
use LemonParser\Ast\Declaration\PrecedenceDeclaration;
use LemonParser\Ast\Declaration\TokenClass;
use LemonParser\Ast\Declaration\TokenDeclaration;
use LemonParser\Ast\Declaration\TypeDeclaration;
use LemonParser\Ast\Declaration\Wildcard;
use LemonParser\Ast\GrammarFile;
use LemonParser\Ast\Location;
use LemonParser\Ast\RhsItem;
use LemonParser\Ast\Symbol;
use LemonParser\Parser;
use LemonParser\Preprocessor\Condition;
use LemonParser\Preprocessor\Exclusion;
use LemonParser\Preprocessor\Preprocessor;
use LemonParser\Printer\DeclarationPrinter;
use LemonParser\Printer\Printer;
use LemonParser\Printer\RulePrinter;
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
use SqlParser\Compiler\LemonGrammarReader;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\Precedence;
use SqlParser\Grammar\PrecedencePolicy;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;

#[CoversClass(LemonGrammarReader::class)]
#[UsesClass(CodeBlock::class)]
#[UsesClass(ArgumentForm::class)]
#[UsesClass(\LemonParser\Ast\Declaration\Associativity::class)]
#[UsesClass(Declaration::class)]
#[UsesClass(Destructor::class)]
#[UsesClass(Directive::class)]
#[UsesClass(DirectiveKeyword::class)]
#[UsesClass(Fallback::class)]
#[UsesClass(PrecedenceDeclaration::class)]
#[UsesClass(TokenClass::class)]
#[UsesClass(TokenDeclaration::class)]
#[UsesClass(TypeDeclaration::class)]
#[UsesClass(Wildcard::class)]
#[UsesClass(GrammarFile::class)]
#[UsesClass(Location::class)]
#[UsesClass(RhsItem::class)]
#[UsesClass(\LemonParser\Ast\Rule::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(Parser::class)]
#[UsesClass(Condition::class)]
#[UsesClass(Exclusion::class)]
#[UsesClass(Preprocessor::class)]
#[UsesClass(DeclarationPrinter::class)]
#[UsesClass(Printer::class)]
#[UsesClass(RulePrinter::class)]
#[UsesClass(CodeReader::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Scanner::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(DeclarationReader::class)]
#[UsesClass(GrammarReader::class)]
#[UsesClass(RuleReader::class)]
#[UsesClass(SymbolListReader::class)]
#[UsesClass(SymbolRegistry::class)]
#[UsesClass(TokenStream::class)]
#[UsesClass(Associativity::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(GrammarBuilder::class)]
#[UsesClass(Precedence::class)]
#[UsesClass(PrecedencePolicy::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class LemonGrammarReaderTest extends TestCase
{
    public function testRead(): void
    {
        $grammar = (new LemonGrammarReader())->read("%token_prefix TK_\n%token SEMI.\n%left PLUS MINUS.\n%fallback ID ABORT AFTER.\n%wildcard ANY.\n%token_class id ID|INDEXED.\n%start_symbol input\nexpr(A) ::= expr(B) PLUS|MINUS expr(C). [PLUS] { A = B + C; }\nexpr ::= NUM.\ninput ::= expr SEMI.\n");

        self::assertSame('input', $grammar->symbols->name($grammar->startSymbol()));
        self::assertSame(PrecedencePolicy::FirstRankedTerminal, $grammar->policy);
        self::assertSame(['$accept', 'expr', 'expr', 'input'], array_map(static fn (Rule $rule): string => $grammar->symbols->name($rule->lhs), $grammar->rules));
        self::assertSame(['expr', 'PLUS|MINUS', 'expr'], array_map(static fn (int $id): string => $grammar->symbols->name($id), $grammar->rules[1]->rhs));
        self::assertSame('PLUS', $grammar->symbols->name($grammar->rules[1]->precedenceSymbol ?? -1));
        self::assertSame('ANY', $grammar->symbols->name($grammar->wildcard ?? -1));
        self::assertCount(2, $grammar->tokenClasses);
        self::assertCount(2, $grammar->fallbacks);
        self::assertSame(Associativity::Left, $grammar->precedenceOf($grammar->symbols->id('MINUS') ?? -1)?->associativity);
    }

    public function testReadSettlesConditionalRegions(): void
    {
        $source = "%ifndef OMIT\ncmd ::= EXTRA.\n%endif\ncmd ::= SELECT.\n";
        $reader = new LemonGrammarReader();

        self::assertCount(3, $reader->read($source)->rules);
        self::assertCount(2, $reader->read($source, ['OMIT'])->rules);
    }

    public function testReadRejectsWhatLemonRejects(): void
    {
        $this->expectException(SyntaxException::class);

        (new LemonGrammarReader())->read("expr ::= expr ? expr.\n");
    }

    public function testDeclaration(): void
    {
        $file = (new Parser())->parse("%token A.\n%right B.\n%fallback.\n%wildcard.\n%name Calc\n%start_symbol s\ns ::= A B.\n");
        $reader = new LemonGrammarReader();
        $builder = new GrammarBuilder();

        $declarations = $file->declarations();

        $reader->declaration($declarations[0], $builder);
        $reader->declaration($declarations[1], $builder);
        $reader->declaration($declarations[2], $builder);
        $reader->declaration($declarations[3], $builder);
        $reader->declaration($declarations[4], $builder);
        $reader->declaration($declarations[5], $builder);
        $builder->rule('s', ['A', 'B'], null);
        $grammar = $builder->build();

        self::assertTrue($builder->isTerminal('A'));
        self::assertSame(Associativity::Right, $grammar->precedenceOf($grammar->symbols->id('B') ?? -1)?->associativity);
        self::assertSame([], $grammar->fallbacks);
        self::assertNull($grammar->wildcard);
        self::assertSame('s', $grammar->symbols->name($grammar->startSymbol()));
    }

    public function testAssociativity(): void
    {
        $reader = new LemonGrammarReader();

        self::assertSame(Associativity::Left, $reader->associativity(\LemonParser\Ast\Declaration\Associativity::Left));
        self::assertSame(Associativity::Right, $reader->associativity(\LemonParser\Ast\Declaration\Associativity::Right));
        self::assertSame(Associativity::NonAssoc, $reader->associativity(\LemonParser\Ast\Declaration\Associativity::NonAssoc));
    }

    public function testRule(): void
    {
        $file = (new Parser())->parse("s ::= A|B c A|B. [A]\nc ::= .\n");
        $reader = new LemonGrammarReader();
        $builder = new GrammarBuilder();

        $reader->rule($file->rules()[0], $builder);
        $reader->rule($file->rules()[1], $builder);
        $grammar = $builder->build();

        self::assertSame(['A|B', 'c', 'A|B'], array_map(static fn (int $id): string => $grammar->symbols->name($id), $grammar->rules[1]->rhs));
        self::assertCount(1, $grammar->tokenClasses);
        self::assertSame('A', $grammar->symbols->name($grammar->rules[1]->precedenceSymbol ?? -1));
        self::assertSame([], $grammar->rules[2]->rhs);
    }

    public function testNames(): void
    {
        $symbols = [new Symbol('A', new Location(1, 1)), new Symbol('b', new Location(1, 3))];

        self::assertSame(['A', 'b'], (new LemonGrammarReader())->names($symbols));
    }
}
