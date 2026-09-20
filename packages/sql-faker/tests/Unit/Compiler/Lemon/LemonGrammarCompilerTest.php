<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Lemon;

use LemonParser\Ast\CodeBlock;
use LemonParser\Ast\Declaration\ArgumentForm;
use LemonParser\Ast\Declaration\Associativity;
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
use LemonParser\Ast\Rule;
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
use SqlFaker\Compiler\GrammarParseException;
use SqlFaker\Compiler\Lemon\LemonGrammarCompiler;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(LemonGrammarCompiler::class)]
#[UsesClass(CodeBlock::class)]
#[UsesClass(ArgumentForm::class)]
#[UsesClass(Associativity::class)]
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
#[UsesClass(Rule::class)]
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
#[UsesClass(GrammarParseException::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[Small]
final class LemonGrammarCompilerTest extends TestCase
{
    public function testCompile(): void
    {
        $grammar = (new LemonGrammarCompiler())->compile("%token_class id ID|INDEXED.\n%left PLUS MINUS.\nexpr(A) ::= expr(B) PLUS|MINUS term(C). [PLUS] { A = B + C; }\nexpr ::= term.\nterm ::= NUM.\nterm ::= id.\nexpr ::= .\n");

        self::assertSame('expr', $grammar->startSymbol);
        self::assertSame(['expr', 'term', 'id'], array_keys($grammar->ruleMap));
        $spell = static fn (Production $production): string => implode(' ', array_map(static fn (\SqlFaker\Grammar\Model\Symbol $symbol): string => ($symbol instanceof Terminal ? 'T:' : 'N:') . $symbol->value(), $production->symbols));
        self::assertSame(['N:expr T:PLUS N:term', 'N:expr T:MINUS N:term', 'N:term', ''], array_map($spell, $grammar->ruleMap['expr']->alternatives));
        self::assertSame(['T:NUM', 'N:id'], array_map($spell, $grammar->ruleMap['term']->alternatives));
        self::assertSame(['T:ID', 'T:INDEXED'], array_map($spell, $grammar->ruleMap['id']->alternatives));
    }

    public function testCompileSettlesConditionalRegions(): void
    {
        $source = "%ifndef OMIT\n  cmd ::= EXTRA.\n%endif\ncmd ::= SELECT.\n";
        $compiler = new LemonGrammarCompiler();

        self::assertCount(2, $compiler->compile($source)->ruleMap['cmd']->alternatives);
        self::assertCount(1, $compiler->compile($source, ['OMIT'])->ruleMap['cmd']->alternatives);
    }

    public function testCompileRejectsAFileWithoutRules(): void
    {
        $this->expectException(GrammarParseException::class);

        (new LemonGrammarCompiler())->compile("%token A.\n");
    }

    public function testExpand(): void
    {
        $file = (new Parser())->parse("s ::= A|B c(X) D|E.\nt ::= .\n");
        $compiler = new LemonGrammarCompiler();

        self::assertSame([['A', 'c', 'D'], ['A', 'c', 'E'], ['B', 'c', 'D'], ['B', 'c', 'E']], $compiler->expand($file->rules()[0]));
        self::assertSame([[]], $compiler->expand($file->rules()[1]));
    }

    public function testTerminals(): void
    {
        $file = (new Parser())->parse("%token A.\n%left B.\n%fallback C D.\n%wildcard Any.\n%token_class cls E|F.\ns ::= A G h Mixed cls.\nh ::= Any.\n");

        self::assertSame(['Any' => true, 'A' => true, 'G' => true], (new LemonGrammarCompiler())->terminals($file));
    }


    public function testIsTokenName(): void
    {
        self::assertTrue(LemonGrammarCompiler::isTokenName('ID'));
        self::assertTrue(LemonGrammarCompiler::isTokenName('JOIN_KW2'));
        self::assertFalse(LemonGrammarCompiler::isTokenName('expr'));
        self::assertFalse(LemonGrammarCompiler::isTokenName('Mixed'));
        self::assertFalse(LemonGrammarCompiler::isTokenName('aID'));
        self::assertFalse(LemonGrammarCompiler::isTokenName('IDa'));
        self::assertFalse(LemonGrammarCompiler::isTokenName(''));
    }
}
