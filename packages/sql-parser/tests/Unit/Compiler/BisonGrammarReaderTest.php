<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler;

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
use SqlParser\Compiler\BisonGrammarReader;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\Precedence;
use SqlParser\Grammar\PrecedencePolicy;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;

#[CoversClass(BisonGrammarReader::class)]
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
#[UsesClass(\BisonParser\Ast\Declaration\Symbols\Associativity::class)]
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
#[UsesClass(\BisonParser\Ast\Rule\Rule::class)]
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
#[UsesClass(Associativity::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(GrammarBuilder::class)]
#[UsesClass(Precedence::class)]
#[UsesClass(PrecedencePolicy::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[Small]
final class BisonGrammarReaderTest extends TestCase
{
    public function testRead(): void
    {
        $grammar = (new BisonGrammarReader())->read("%token NUM STR\n%left '+' '-'\n%right UMINUS\n%start expr\n%expect 1\n%%\nexpr: expr '+' expr | '-' expr %prec UMINUS | NUM { \$\$ = \$1; } | \"str\" ;\n");

        self::assertSame('expr', $grammar->symbols->name($grammar->startSymbol()));
        self::assertSame(1, $grammar->expectedConflicts);
        self::assertSame(PrecedencePolicy::LastTerminal, $grammar->policy);
        self::assertSame(['$accept', 'expr', 'expr', 'expr', 'expr'], array_map(static fn (Rule $rule): string => $grammar->symbols->name($rule->lhs), $grammar->rules));
        self::assertSame(['-', 'expr'], array_map(static fn (int $id): string => $grammar->symbols->name($id), $grammar->rules[2]->rhs));
        self::assertSame('UMINUS', $grammar->symbols->name($grammar->rules[2]->precedenceSymbol ?? -1));
        self::assertSame('str', $grammar->symbols->name($grammar->rules[4]->rhs[0]));
        self::assertNotNull($grammar->symbols->id('error'));
        self::assertSame(Associativity::Right, $grammar->precedenceOf($grammar->symbols->id('UMINUS') ?? -1)?->associativity);
    }

    public function testReadNumbersMidRuleActionsBeforeTheirRule(): void
    {
        $grammar = (new BisonGrammarReader())->read("%token A B C\n%%\ns: A { one(); } B { two(); } C { last(); } | %empty ;\nt: s ;\n");

        self::assertSame(['$accept', '$@1', '$@2', 's', 's', 't'], array_map(static fn (Rule $rule): string => $grammar->symbols->name($rule->lhs), $grammar->rules));
        self::assertSame(['A', '$@1', 'B', '$@2', 'C'], array_map(static fn (int $id): string => $grammar->symbols->name($id), $grammar->rules[3]->rhs));
        self::assertTrue($grammar->rules[1]->hidden);
        self::assertSame([], $grammar->rules[4]->rhs);
    }

    public function testReadRejectsWhatBisonRejects(): void
    {
        $this->expectException(SyntaxException::class);

        (new BisonGrammarReader())->read("%token A\nexpr: A;\n");
    }

    public function testDeclaration(): void
    {
        $file = (new Parser())->parse("%token <int> NUM 258 \"number\"\n%nonassoc EQ\n%precedence LOW\n%type <t> expr\n%start expr\n%expect-rr 2\n%expect 3\n%%\nexpr: NUM;\n");
        $reader = new BisonGrammarReader();
        $builder = new GrammarBuilder();
        $builder->policy(PrecedencePolicy::LastTerminal);

        $declarations = $file->allDeclarations();

        $reader->declaration($declarations[0], $builder);
        $reader->declaration($declarations[1], $builder);
        $reader->declaration($declarations[2], $builder);
        $reader->declaration($declarations[3], $builder);
        $reader->declaration($declarations[4], $builder);
        $reader->declaration($declarations[5], $builder);
        $reader->declaration($declarations[6], $builder);
        $builder->rule('other', ['NUM'], null);
        $builder->rule('expr', ['NUM'], null);
        $grammar = $builder->build();

        self::assertTrue($builder->isTerminal('NUM'));
        self::assertTrue($builder->isTerminal('EQ'));
        self::assertSame(3, $grammar->expectedConflicts);
        self::assertSame('expr', $grammar->symbols->name($grammar->startSymbol()));
        self::assertSame(Associativity::Precedence, $grammar->precedenceOf($grammar->symbols->id('LOW') ?? -1)?->associativity);
    }

    public function testAssociativity(): void
    {
        $reader = new BisonGrammarReader();

        self::assertSame(Associativity::Left, $reader->associativity(\BisonParser\Ast\Declaration\Symbols\Associativity::Left));
        self::assertSame(Associativity::Right, $reader->associativity(\BisonParser\Ast\Declaration\Symbols\Associativity::Right));
        self::assertSame(Associativity::NonAssoc, $reader->associativity(\BisonParser\Ast\Declaration\Symbols\Associativity::NonAssoc));
        self::assertSame(Associativity::Precedence, $reader->associativity(\BisonParser\Ast\Declaration\Symbols\Associativity::Precedence));
    }

    public function testAlternative(): void
    {
        $file = (new Parser())->parse("%token A\n%%\ns: A[x] <int>{ mid(); } 'b' %dprec 2 %merge <m> %prec A { end(); } ;\n");
        $reader = new BisonGrammarReader();
        $builder = new GrammarBuilder();
        $builder->terminal('A');

        $reader->alternative('s', $file->rules()[0]->alternatives[0], $builder);
        $grammar = $builder->build();

        self::assertSame(['A', '$@1', 'b'], array_map(static fn (int $id): string => $grammar->symbols->name($id), $grammar->rules[2]->rhs));
        self::assertSame('A', $grammar->symbols->name($grammar->rules[2]->precedenceSymbol ?? -1));
        self::assertTrue($builder->isTerminal('b'));
    }

    public function testContinues(): void
    {
        $file = (new Parser())->parse("%token A\n%%\ns: { a } A { b } %prec A { c } ;\n");
        $alternative = $file->rules()[0]->alternatives[0];
        $reader = new BisonGrammarReader();

        self::assertTrue($reader->continues($alternative, 0));
        self::assertTrue($reader->continues($alternative, 1));
        self::assertTrue($reader->continues($alternative, 2));
        self::assertFalse($reader->continues($alternative, 4));
    }

    public function testContinuesLooksOnlyAtWhatFollowsTheItem(): void
    {
        $reader = new BisonGrammarReader();
        $parser = new Parser();
        $symbol = $parser->parse("%token A\n%%\ns: { a } A ;\n")->rules()[0]->alternatives[0];
        $action = $parser->parse("%token A\n%%\ns: { a } { b } ;\n")->rules()[0]->alternatives[0];
        $predicate = $parser->parse("%token A\n%%\ns: { a } %?{ p } ;\n")->rules()[0]->alternatives[0];
        $modifiers = $parser->parse("%token A\n%%\ns: { a } %prec A %dprec 1 ;\n")->rules()[0]->alternatives[0];

        self::assertTrue($reader->continues($symbol, 0));
        self::assertTrue($reader->continues($action, 0));
        self::assertTrue($reader->continues($predicate, 0));
        self::assertFalse($reader->continues($modifiers, 0));
        self::assertFalse($reader->continues($symbol, 1));
    }

    public function testReadNumbersMidRuleActionsFromOneForEveryFile(): void
    {
        $reader = new BisonGrammarReader();
        $source = "%token A B\n%%\ns: A { act(); } B ;\n";

        $reader->read($source);
        $grammar = $reader->read($source);

        self::assertSame('$@1', $grammar->symbols->name($grammar->rules[1]->lhs));
    }

    public function testMidRule(): void
    {
        $reader = new BisonGrammarReader();
        $builder = new GrammarBuilder();

        $first = $reader->midRule($builder);
        $second = $reader->midRule($builder);
        $builder->rule('s', [$first, $second], null);
        $grammar = $builder->build();

        self::assertSame(['$@1', '$@2'], [$first, $second]);
        self::assertTrue($grammar->rules[1]->hidden);
        self::assertSame([], $grammar->rules[1]->rhs);
    }
}
