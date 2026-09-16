<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Bison;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\Bison\BisonDeclarations;
use SqlParser\Compiler\Bison\BisonScanner;
use SqlParser\Compiler\Bison\BisonToken;
use SqlParser\Compiler\Bison\BisonTokenKind;
use SqlParser\Compiler\Bison\BisonTokens;
use SqlParser\Compiler\CodeBlockReader;
use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Compiler\SourceReader;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\Precedence;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;

#[CoversClass(BisonDeclarations::class)]
#[UsesClass(BisonScanner::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokens::class)]
#[UsesClass(CodeBlockReader::class)]
#[UsesClass(GrammarSourceException::class)]
#[UsesClass(SourceReader::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(GrammarBuilder::class)]
#[UsesClass(Precedence::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[Small]
final class BisonDeclarationsTest extends TestCase
{
    public function testReadRecordsTokensPrecedenceStartAndExpectation(): void
    {
        $source = "%pure-parser\n%union { int i; }\n%token <str> IDENT 258 \"identifier\" NUM\n%left '+' '-'\n%right UMINUS\n%nonassoc '='\n%precedence LOW\n%type <node> expr\n%expect 3\n%start expr\n%%\nexpr: NUM;";
        $tokens = new BisonTokens((new BisonScanner())->scan($source));
        $builder = new GrammarBuilder();
        (new BisonDeclarations())->read($tokens, $builder);
        $builder->rule('expr', ['NUM']);
        $grammar = $builder->build();

        self::assertSame(BisonTokenKind::Identifier, $tokens->peek()?->kind);
        self::assertSame(['$end', 'IDENT', 'NUM', '+', '-', 'UMINUS', '=', 'LOW'], $grammar->symbols->terminals());
        self::assertSame(Associativity::Left, $grammar->precedenceOf($grammar->symbols->id('-') ?? -1)?->associativity);
        self::assertSame(2, $grammar->precedenceOf($grammar->symbols->id('UMINUS') ?? -1)?->level);
        self::assertSame(Associativity::NonAssoc, $grammar->precedenceOf($grammar->symbols->id('=') ?? -1)?->associativity);
        self::assertSame(Associativity::Precedence, $grammar->precedenceOf($grammar->symbols->id('LOW') ?? -1)?->associativity);
        self::assertSame(3, $grammar->expectedConflicts);
        self::assertSame('expr', $grammar->symbols->name($grammar->startSymbol()));
    }

    public function testReadRejectsAMissingStartSymbol(): void
    {
        $tokens = new BisonTokens([new BisonToken(BisonTokenKind::Directive, 'start', 1), new BisonToken(BisonTokenKind::Section, '%%', 2)]);

        $this->expectException(GrammarSourceException::class);

        (new BisonDeclarations())->read($tokens, new GrammarBuilder());
    }

    public function testAssociativity(): void
    {
        $declarations = new BisonDeclarations();

        self::assertSame(Associativity::Left, $declarations->associativity('left'));
        self::assertSame(Associativity::Right, $declarations->associativity('right'));
        self::assertSame(Associativity::NonAssoc, $declarations->associativity('nonassoc'));
        self::assertSame(Associativity::Precedence, $declarations->associativity('precedence'));
        self::assertNull($declarations->associativity('token'));
    }

    public function testSymbols(): void
    {
        $tokens = new BisonTokens((new BisonScanner())->scan("<tag> A 258 \"a\" 'b' %left C"));

        self::assertSame(['A', 'b'], (new BisonDeclarations())->symbols($tokens));
        self::assertSame('left', $tokens->peek()?->text);
    }

    public function testSkipArguments(): void
    {
        $tokens = new BisonTokens((new BisonScanner())->scan('{ code } <tag> x 1 %token A %%'));
        (new BisonDeclarations())->skipArguments($tokens);

        self::assertSame('token', $tokens->peek()?->text);
    }
}
