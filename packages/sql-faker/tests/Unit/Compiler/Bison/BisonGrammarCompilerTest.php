<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Bison;

use BisonParser\Ast\Declaration\Code;
use BisonParser\Ast\Declaration\CodeProps;
use BisonParser\Ast\Declaration\Declaration;
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
use BisonParser\Ast\Epilogue;
use BisonParser\Ast\GrammarFile;
use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\Action;
use BisonParser\Ast\Rule\Alternative;
use BisonParser\Ast\Rule\DprecItem;
use BisonParser\Ast\Rule\EmptyItem;
use BisonParser\Ast\Rule\ExpectItem;
use BisonParser\Ast\Rule\MergeItem;
use BisonParser\Ast\Rule\PrecItem;
use BisonParser\Ast\Rule\Predicate;
use BisonParser\Ast\Rule\RhsItem;
use BisonParser\Ast\Rule\Rule;
use BisonParser\Ast\Rule\SymbolItem;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use BisonParser\Ast\Tag;
use BisonParser\Parser;
use BisonParser\Printer\DeclarationPrinter;
use BisonParser\Printer\Printer;
use BisonParser\Printer\RhsPrinter;
use BisonParser\Printer\SymbolPrinter;
use BisonParser\Scanner\CodeReader;
use BisonParser\Scanner\Cursor;
use BisonParser\Scanner\Directives;
use BisonParser\Scanner\Escapes;
use BisonParser\Scanner\Scanner;
use BisonParser\Scanner\Token;
use BisonParser\Scanner\TokenKind;
use BisonParser\Syntax\DeclarationParser;
use BisonParser\Syntax\GrammarParser;
use BisonParser\Syntax\RuleParser;
use BisonParser\Syntax\SymbolListParser;
use BisonParser\Syntax\TokenStream;
use BisonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\BisonGrammarCompiler;
use SqlFaker\Compiler\UnknownSymbolException;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(BisonGrammarCompiler::class)]
#[UsesClass(Code::class)]
#[UsesClass(CodeProps::class)]
#[UsesClass(Declaration::class)]
#[UsesClass(Define::class)]
#[UsesClass(DefineForm::class)]
#[UsesClass(Expect::class)]
#[UsesClass(Flag::class)]
#[UsesClass(InitialAction::class)]
#[UsesClass(Option::class)]
#[UsesClass(Param::class)]
#[UsesClass(ParamKind::class)]
#[UsesClass(Prologue::class)]
#[UsesClass(Start::class)]
#[UsesClass(Alias::class)]
#[UsesClass(Associativity::class)]
#[UsesClass(PrecedenceDeclaration::class)]
#[UsesClass(SymbolClass::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(SymbolEntry::class)]
#[UsesClass(UnionDeclaration::class)]
#[UsesClass(Epilogue::class)]
#[UsesClass(GrammarFile::class)]
#[UsesClass(Location::class)]
#[UsesClass(Action::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(DprecItem::class)]
#[UsesClass(EmptyItem::class)]
#[UsesClass(ExpectItem::class)]
#[UsesClass(MergeItem::class)]
#[UsesClass(PrecItem::class)]
#[UsesClass(Predicate::class)]
#[UsesClass(RhsItem::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolItem::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolKind::class)]
#[UsesClass(Tag::class)]
#[UsesClass(Parser::class)]
#[UsesClass(DeclarationPrinter::class)]
#[UsesClass(Printer::class)]
#[UsesClass(RhsPrinter::class)]
#[UsesClass(SymbolPrinter::class)]
#[UsesClass(CodeReader::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Directives::class)]
#[UsesClass(Escapes::class)]
#[UsesClass(Scanner::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(DeclarationParser::class)]
#[UsesClass(GrammarParser::class)]
#[UsesClass(RuleParser::class)]
#[UsesClass(SymbolListParser::class)]
#[UsesClass(TokenStream::class)]
#[UsesClass(UnknownSymbolException::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[Small]
final class BisonGrammarCompilerTest extends TestCase
{
    public function testCompile(): void
    {
        $grammar = (new BisonGrammarCompiler())->compile("%token NUM STR\n%start expr\n%%\nexpr: expr '+' term { \$\$ = \$1; } | term %prec NUM | \"lit\" ;\nterm: NUM ;\nexpr: STR ;\n");

        self::assertSame('expr', $grammar->startSymbol);
        self::assertSame(['expr', 'term'], array_keys($grammar->ruleMap));
        self::assertSame(
            [[[NonTerminal::class, 'expr'], [Terminal::class, '+'], [NonTerminal::class, 'term']], [[NonTerminal::class, 'term']], [], [[Terminal::class, 'STR']]],
            array_map(static fn (Production $production): array => array_map(static fn (\SqlFaker\Grammar\Model\Symbol $symbol): array => [$symbol::class, $symbol->value()], $production->symbols), $grammar->ruleMap['expr']->alternatives),
        );
    }

    public function testCompileTakesTheFirstRuleAsStartSymbolWithoutStart(): void
    {
        $grammar = (new BisonGrammarCompiler())->compile("%token NUM\n%%\nterm: NUM ;\nexpr: term ;\n");

        self::assertSame('term', $grammar->startSymbol);
    }

    public function testCompileRejectsAFileWithoutRules(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Expected a rule but found end of file at 3:1');

        (new BisonGrammarCompiler())->compile("%token NUM\n%%\n");
    }

    public function testCompileRejectsAnUndeclaredName(): void
    {
        $this->expectException(UnknownSymbolException::class);

        (new BisonGrammarCompiler())->compile("%token NUM\n%%\nexpr: NUM PLUS ;\n");
    }

    public function testDeclaredTokens(): void
    {
        $file = (new Parser())->parse("%left '+' MINUS\n%token <int> NUM 258 \"number\" STR\n%type <t> expr\n%token LATE\n%%\nexpr: NUM ;\n");

        self::assertSame(['NUM' => true, 'STR' => true, 'LATE' => true], (new BisonGrammarCompiler())->declaredTokens($file));
    }

    public function testSymbols(): void
    {
        $file = (new Parser())->parse("%token NUM\n%%\nexpr: expr '+' \"skipped\" NUM { act(); } ;\n");

        $symbols = (new BisonGrammarCompiler())->symbols($file->rules()[0]->alternatives[0], ['expr' => true], ['NUM' => true]);

        self::assertSame([[NonTerminal::class, 'expr'], [Terminal::class, '+'], [Terminal::class, 'NUM']], array_map(static fn (\SqlFaker\Grammar\Model\Symbol $symbol): array => [$symbol::class, $symbol->value()], $symbols));
    }

    public function testSymbolsRejectsAnUnknownName(): void
    {
        $file = (new Parser())->parse("%token NUM\n%%\nexpr: other ;\n");

        $this->expectException(UnknownSymbolException::class);

        (new BisonGrammarCompiler())->symbols($file->rules()[0]->alternatives[0], ['expr' => true], ['NUM' => true]);
    }

    public function testStartSymbol(): void
    {
        $compiler = new BisonGrammarCompiler();
        $parser = new Parser();

        self::assertSame('expr', $compiler->startSymbol($parser->parse("%token NUM\n%start expr\n%%\nterm: NUM ;\nexpr: term ;\n")));
        self::assertSame('term', $compiler->startSymbol($parser->parse("%token NUM\n%%\nterm: NUM ;\nexpr: term ;\n")));
    }
}
