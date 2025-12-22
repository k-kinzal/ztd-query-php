<?php

declare(strict_types=1);

namespace SqlFaker\Tests\Unit\MySql\Bison;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\MySql\Bison\Ast\BisonDefineDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonExpectDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonParamDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonPrecedenceDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonStartDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonSymbolType;
use SqlFaker\MySql\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTypeDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonUnknownDeclaration;
use SqlFaker\MySql\Bison\BisonParser;

final class BisonParserTest extends TestCase
{
    public function testParseMinimalGrammar(): void
    {
        $input = <<<'BISON'
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertNull($ast->prologue);
        self::assertSame([], $ast->declarations);
        self::assertCount(1, $ast->rules);
        self::assertSame('rule', $ast->rules[0]->name);
        self::assertNull($ast->epilogue);
    }

    public function testParseNoRulesThrowsException(): void
    {
        $input = <<<'BISON'
%token TOKEN
%%
BISON;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No grammar rules parsed.');

        (new BisonParser())->parse($input);
    }

    public function testParseWithPrologue(): void
    {
        $input = <<<'BISON'
%{
#include <stdio.h>
%}
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertNotNull($ast->prologue);
        self::assertStringContainsString('#include <stdio.h>', $ast->prologue);
    }

    public function testParseWithEpilogue(): void
    {
        $input = <<<'BISON'
%%
rule: TOKEN;
%%
epilogue_code
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertNotNull($ast->epilogue);
        self::assertStringContainsString('epilogue_code', $ast->epilogue);
    }

    public function testParseWithoutEpilogue(): void
    {
        $input = <<<'BISON'
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertNull($ast->epilogue);
    }

    public function testParseStartDirective(): void
    {
        $input = <<<'BISON'
%start program
%%
program: statement;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->declarations);
        self::assertInstanceOf(BisonStartDeclaration::class, $ast->declarations[0]);
        self::assertSame('program', $ast->declarations[0]->symbol);
    }

    public function testParseStartDirectiveWithoutIdentifier(): void
    {
        $input = <<<'BISON'
%start
%token TOKEN
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->declarations);
        self::assertInstanceOf(BisonTokenDeclaration::class, $ast->declarations[0]);
    }

    public function testParseTokenDirective(): void
    {
        $input = <<<'BISON'
%token TOKEN1 TOKEN2
%%
rule: TOKEN1;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->declarations);
        self::assertInstanceOf(BisonTokenDeclaration::class, $ast->declarations[0]);
        $decl = $ast->declarations[0];
        self::assertNull($decl->typeTag);
        self::assertCount(2, $decl->tokens);
        self::assertSame('TOKEN1', $decl->tokens[0]->name);
        self::assertSame('TOKEN2', $decl->tokens[1]->name);
    }

    public function testParseTokenDirectiveWithTypeTag(): void
    {
        $input = <<<'BISON'
%token <num> NUMBER
%%
rule: NUMBER;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertInstanceOf(BisonTokenDeclaration::class, $ast->declarations[0]);
        self::assertSame('num', $ast->declarations[0]->typeTag);
    }

    public function testParseTokenDirectiveWithNumber(): void
    {
        $input = <<<'BISON'
%token TOKEN 258
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertInstanceOf(BisonTokenDeclaration::class, $ast->declarations[0]);
        self::assertSame(258, $ast->declarations[0]->tokens[0]->number);
    }

    public function testParseTokenDirectiveWithAlias(): void
    {
        $input = <<<'BISON'
%token TOKEN "token"
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertInstanceOf(BisonTokenDeclaration::class, $ast->declarations[0]);
        self::assertSame('token', $ast->declarations[0]->tokens[0]->alias);
    }

    public function testParseTokenDirectiveWithNumberAndAlias(): void
    {
        $input = <<<'BISON'
%token TOKEN 258 "token"
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertInstanceOf(BisonTokenDeclaration::class, $ast->declarations[0]);
        $token = $ast->declarations[0]->tokens[0];
        self::assertSame('TOKEN', $token->name);
        self::assertSame(258, $token->number);
        self::assertSame('token', $token->alias);
    }

    public function testParseTypeDirective(): void
    {
        $input = <<<'BISON'
%type <node> expr term
%%
expr: term;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->declarations);
        self::assertInstanceOf(BisonTypeDeclaration::class, $ast->declarations[0]);
        $decl = $ast->declarations[0];
        self::assertSame('node', $decl->typeTag);
        self::assertSame(['expr', 'term'], $decl->symbols);
    }

    public function testParseTypeDirectiveWithoutTypeTag(): void
    {
        $input = <<<'BISON'
%type expr
%token TOKEN
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->declarations);
        self::assertInstanceOf(BisonTokenDeclaration::class, $ast->declarations[0]);
    }

    #[DataProvider('providerPrecedenceDirectives')]
    public function testParsePrecedenceDirective(string $directive, string $expectedAssociativity): void
    {
        $input = <<<BISON
{$directive} '+' '-'
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->declarations);
        self::assertInstanceOf(BisonPrecedenceDeclaration::class, $ast->declarations[0]);
        $decl = $ast->declarations[0];
        self::assertSame($expectedAssociativity, $decl->associativity);
        self::assertSame(['+', '-'], $decl->symbols);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerPrecedenceDirectives(): iterable
    {
        yield 'left' => ['%left', 'left'];
        yield 'right' => ['%right', 'right'];
        yield 'nonassoc' => ['%nonassoc', 'nonassoc'];
        yield 'precedence' => ['%precedence', 'precedence'];
    }

    public function testParsePrecedenceDirectiveWithTypeTag(): void
    {
        $input = <<<'BISON'
%left <op> PLUS MINUS
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertInstanceOf(BisonPrecedenceDeclaration::class, $ast->declarations[0]);
        self::assertSame('op', $ast->declarations[0]->typeTag);
    }

    public function testParsePrecedenceDirectiveWithIdentifiers(): void
    {
        $input = <<<'BISON'
%left PLUS MINUS
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertInstanceOf(BisonPrecedenceDeclaration::class, $ast->declarations[0]);
        self::assertSame(['PLUS', 'MINUS'], $ast->declarations[0]->symbols);
    }

    public function testParseParseParamDirective(): void
    {
        $input = <<<'BISON'
%parse-param { void *scanner }
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->declarations);
        self::assertInstanceOf(BisonParamDeclaration::class, $ast->declarations[0]);
        $decl = $ast->declarations[0];
        self::assertSame('parse-param', $decl->kind);
        self::assertStringContainsString('void *scanner', $decl->code);
    }

    public function testParseLexParamDirective(): void
    {
        $input = <<<'BISON'
%lex-param { void *scanner }
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertInstanceOf(BisonParamDeclaration::class, $ast->declarations[0]);
        self::assertSame('lex-param', $ast->declarations[0]->kind);
    }

    public function testParseParamDirectiveWithoutAction(): void
    {
        $input = <<<'BISON'
%parse-param
%token TOKEN
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->declarations);
        self::assertInstanceOf(BisonTokenDeclaration::class, $ast->declarations[0]);
    }

    public function testParseExpectDirective(): void
    {
        $input = <<<'BISON'
%expect 5
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->declarations);
        self::assertInstanceOf(BisonExpectDeclaration::class, $ast->declarations[0]);
        self::assertSame(5, $ast->declarations[0]->count);
    }

    public function testParseExpectDirectiveWithoutNumber(): void
    {
        $input = <<<'BISON'
%expect
%token TOKEN
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->declarations);
        self::assertInstanceOf(BisonTokenDeclaration::class, $ast->declarations[0]);
    }

    public function testParseDefineDirectiveWithIdentifierValue(): void
    {
        $input = <<<'BISON'
%define api.pure full
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->declarations);
        self::assertInstanceOf(BisonDefineDeclaration::class, $ast->declarations[0]);
        $decl = $ast->declarations[0];
        self::assertSame('api.pure', $decl->name);
        self::assertSame('full', $decl->value);
    }

    public function testParseDefineDirectiveWithStringValue(): void
    {
        $input = <<<'BISON'
%define api.prefix "yy"
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertInstanceOf(BisonDefineDeclaration::class, $ast->declarations[0]);
        self::assertSame('yy', $ast->declarations[0]->value);
    }

    public function testParseDefineDirectiveWithNumberValue(): void
    {
        $input = <<<'BISON'
%define parse.lac 1
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertInstanceOf(BisonDefineDeclaration::class, $ast->declarations[0]);
        self::assertSame('1', $ast->declarations[0]->value);
    }

    public function testParseDefineDirectiveWithoutValue(): void
    {
        $input = <<<'BISON'
%define api.pure
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertInstanceOf(BisonDefineDeclaration::class, $ast->declarations[0]);
        self::assertSame('api.pure', $ast->declarations[0]->name);
        self::assertNull($ast->declarations[0]->value);
    }

    public function testParseDefineDirectiveWithoutIdentifier(): void
    {
        $input = <<<'BISON'
%define
%token TOKEN
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->declarations);
        self::assertInstanceOf(BisonTokenDeclaration::class, $ast->declarations[0]);
    }

    public function testParseUnknownDirective(): void
    {
        $input = <<<'BISON'
%unknown foo bar
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->declarations);
        self::assertInstanceOf(BisonUnknownDeclaration::class, $ast->declarations[0]);
        $decl = $ast->declarations[0];
        self::assertSame('%unknown', $decl->directive);
        self::assertSame('foo bar', $decl->content);
    }

    public function testParseRuleSingleAlternative(): void
    {
        $input = <<<'BISON'
%%
rule: A B C;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->rules);
        $rule = $ast->rules[0];
        self::assertSame('rule', $rule->name);
        self::assertCount(1, $rule->alternatives);
        self::assertCount(3, $rule->alternatives[0]->symbols);
    }

    public function testParseRuleMultipleAlternatives(): void
    {
        $input = <<<'BISON'
%%
rule: A | B | C;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->rules);
        self::assertCount(3, $ast->rules[0]->alternatives);
    }

    public function testParseRuleWithAction(): void
    {
        $input = <<<'BISON'
%%
rule: A { $$ = $1; };
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertNotNull($ast->rules[0]->alternatives[0]->action);
        self::assertStringContainsString('$$ = $1', $ast->rules[0]->alternatives[0]->action);
    }

    public function testParseRuleWithPrecIdentifier(): void
    {
        $input = <<<'BISON'
%%
rule: A %prec UMINUS;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertSame('UMINUS', $ast->rules[0]->alternatives[0]->prec);
    }

    public function testParseRuleWithPrecCharLiteral(): void
    {
        $input = <<<'BISON'
%%
rule: A %prec '-';
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertSame('-', $ast->rules[0]->alternatives[0]->prec);
    }

    public function testParseRuleWithPrecWithoutSymbol(): void
    {
        $input = <<<'BISON'
%%
rule: A %prec;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertNull($ast->rules[0]->alternatives[0]->prec);
    }

    public function testParseRuleWithDprec(): void
    {
        $input = <<<'BISON'
%%
rule: A %dprec 1;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertSame(1, $ast->rules[0]->alternatives[0]->dprec);
    }

    public function testParseRuleWithDprecWithoutNumber(): void
    {
        $input = <<<'BISON'
%%
rule: A %dprec;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertNull($ast->rules[0]->alternatives[0]->dprec);
    }

    public function testParseRuleWithMerge(): void
    {
        $input = <<<'BISON'
%%
rule: A %merge <merge_func>;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertSame('merge_func', $ast->rules[0]->alternatives[0]->merge);
    }

    public function testParseRuleWithMergeWithoutTypeTag(): void
    {
        $input = <<<'BISON'
%%
rule: A %merge;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertNull($ast->rules[0]->alternatives[0]->merge);
    }

    public function testParseRuleWithEmpty(): void
    {
        $input = <<<'BISON'
%%
rule: %empty;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->rules);
        self::assertCount(0, $ast->rules[0]->alternatives[0]->symbols);
    }

    public function testParseRuleWithCharLiteralSymbol(): void
    {
        $input = <<<'BISON'
%%
rule: '+' A '-';
BISON;

        $ast = (new BisonParser())->parse($input);

        $symbols = $ast->rules[0]->alternatives[0]->symbols;
        self::assertCount(3, $symbols);
        self::assertSame(BisonSymbolType::CharLiteral, $symbols[0]->type);
        self::assertSame('+', $symbols[0]->value);
        self::assertSame(BisonSymbolType::Identifier, $symbols[1]->type);
        self::assertSame(BisonSymbolType::CharLiteral, $symbols[2]->type);
    }

    public function testParseRuleTerminatedBySemicolon(): void
    {
        $input = <<<'BISON'
%%
rule: A;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->rules);
    }

    public function testParseRuleTerminatedByNextRule(): void
    {
        $input = <<<'BISON'
%%
rule1: A
rule2: B;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(2, $ast->rules);
        self::assertSame('rule1', $ast->rules[0]->name);
        self::assertSame('rule2', $ast->rules[1]->name);
    }

    public function testParseRuleTerminatedByPercentPercent(): void
    {
        $input = <<<'BISON'
%%
rule: A
%%
epilogue
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->rules);
        self::assertNotNull($ast->epilogue);
    }

    public function testParseRuleTerminatedByEof(): void
    {
        $input = <<<'BISON'
%%
rule: A
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->rules);
    }

    public function testParseIdentifierNotFollowedByColonIsSkipped(): void
    {
        $input = <<<'BISON'
%%
orphan
rule: A;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->rules);
        self::assertSame('rule', $ast->rules[0]->name);
    }

    public function testParseMultipleRules(): void
    {
        $input = <<<'BISON'
%%
rule1: A;
rule2: B;
rule3: C;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(3, $ast->rules);
    }

    public function testParseUnknownDirectiveInRule(): void
    {
        $input = <<<'BISON'
%%
rule: A %unknown B;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->rules);
        $symbols = $ast->rules[0]->alternatives[0]->symbols;
        self::assertCount(2, $symbols);
        self::assertSame('A', $symbols[0]->value);
        self::assertSame('B', $symbols[1]->value);
    }

    public function testParseComplexGrammar(): void
    {
        $input = <<<'BISON'
%{
#include <stdio.h>
%}

%token <num> NUMBER
%token <str> STRING
%token PLUS MINUS

%type <node> expr term

%left PLUS MINUS
%right UMINUS

%start program

%%

program: expr;

expr: expr PLUS term { $$ = $1 + $3; }
    | expr MINUS term { $$ = $1 - $3; }
    | term
    ;

term: NUMBER
    | '-' term %prec UMINUS { $$ = -$2; }
    ;

%%

epilogue_section
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertNotNull($ast->prologue);
        self::assertCount(7, $ast->declarations);
        self::assertCount(3, $ast->rules);
        self::assertNotNull($ast->epilogue);
    }

    public function testParseFileSuccess(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'bison_test_');
        self::assertNotFalse($tempFile);

        try {
            file_put_contents($tempFile, "%%\nrule: A;");

            $ast = (new BisonParser())->parseFile($tempFile);

            self::assertCount(1, $ast->rules);
        } finally {
            unlink($tempFile);
        }
    }

    public function testParseFileNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to read:');

        @(new BisonParser())->parseFile('/nonexistent/path/file.yy');
    }

    public function testParseEmptyDeclarationsSection(): void
    {
        $input = <<<'BISON'
%%
rule: A;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertSame([], $ast->declarations);
    }

    public function testParseSkipsUnknownTokensInDeclarations(): void
    {
        $input = <<<'BISON'
123
%token TOKEN
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->declarations);
        self::assertInstanceOf(BisonTokenDeclaration::class, $ast->declarations[0]);
    }

    public function testParseSkipsUnknownTokensInRules(): void
    {
        $input = <<<'BISON'
%%
123
rule: A;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->rules);
    }

    public function testParseMultipleDeclarations(): void
    {
        $input = <<<'BISON'
%token TOKEN1
%token TOKEN2
%start rule
%%
rule: TOKEN1;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(3, $ast->declarations);
    }

    public function testParseTokenDirectiveSkipsNonIdentifiers(): void
    {
        $input = <<<'BISON'
%token <type> 123 TOKEN
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertInstanceOf(BisonTokenDeclaration::class, $ast->declarations[0]);
        self::assertCount(1, $ast->declarations[0]->tokens);
        self::assertSame('TOKEN', $ast->declarations[0]->tokens[0]->name);
    }

    public function testParseTypeDirectiveSkipsNonIdentifiers(): void
    {
        $input = <<<'BISON'
%type <node> 123 expr
%%
expr: A;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertInstanceOf(BisonTypeDeclaration::class, $ast->declarations[0]);
        self::assertSame(['expr'], $ast->declarations[0]->symbols);
    }

    public function testParsePrecedenceDirectiveSkipsNonSymbols(): void
    {
        $input = <<<'BISON'
%left 123 PLUS
%%
rule: A;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertInstanceOf(BisonPrecedenceDeclaration::class, $ast->declarations[0]);
        self::assertSame(['PLUS'], $ast->declarations[0]->symbols);
    }

    public function testParseRuleSkipsUnknownTokens(): void
    {
        $input = <<<'BISON'
%%
rule: A 123 B;
BISON;

        $ast = (new BisonParser())->parse($input);

        $symbols = $ast->rules[0]->alternatives[0]->symbols;
        self::assertCount(2, $symbols);
        self::assertSame('A', $symbols[0]->value);
        self::assertSame('B', $symbols[1]->value);
    }

    public function testParseEmptyTokenDirective(): void
    {
        $input = <<<'BISON'
%token
%start rule
%%
rule: A;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(2, $ast->declarations);
        self::assertInstanceOf(BisonTokenDeclaration::class, $ast->declarations[0]);
        self::assertCount(0, $ast->declarations[0]->tokens);
    }

    public function testParseDeclarationsSectionEndsAtEof(): void
    {
        $input = '%token TOKEN';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No grammar rules parsed.');

        (new BisonParser())->parse($input);
    }

    public function testParseRulesSectionEndsAtEof(): void
    {
        $input = <<<'BISON'
%%
rule: A
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(1, $ast->rules);
        self::assertNull($ast->epilogue);
    }

    public function testParseMultipleProloguesLastWins(): void
    {
        $input = <<<'BISON'
%{
first_prologue
%}
%{
second_prologue
%}
%%
rule: A;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertNotNull($ast->prologue);
        self::assertStringContainsString('second_prologue', $ast->prologue);
        self::assertStringNotContainsString('first_prologue', $ast->prologue);
    }

    public function testParsePrologueAfterDeclarations(): void
    {
        $input = <<<'BISON'
%token TOKEN
%{
prologue_after
%}
%start rule
%%
rule: TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertNotNull($ast->prologue);
        self::assertStringContainsString('prologue_after', $ast->prologue);
        self::assertCount(2, $ast->declarations);
    }

    public function testParseMultipleActionsLastWins(): void
    {
        $input = <<<'BISON'
%%
rule: A { first_action } { second_action };
BISON;

        $ast = (new BisonParser())->parse($input);

        $action = $ast->rules[0]->alternatives[0]->action;
        self::assertNotNull($action);
        self::assertStringContainsString('second_action', $action);
        self::assertStringNotContainsString('first_action', $action);
    }

    public function testParseMidRuleAction(): void
    {
        $input = <<<'BISON'
%%
rule: A { mid_action } B;
BISON;

        $ast = (new BisonParser())->parse($input);

        $alt = $ast->rules[0]->alternatives[0];
        self::assertCount(2, $alt->symbols);
        self::assertSame('A', $alt->symbols[0]->value);
        self::assertSame('B', $alt->symbols[1]->value);
        self::assertNotNull($alt->action);
        self::assertStringContainsString('mid_action', $alt->action);
    }

    public function testParseEmptyAlternativeWithoutEmpty(): void
    {
        $input = <<<'BISON'
%%
rule: A | | B;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(3, $ast->rules[0]->alternatives);
        self::assertCount(1, $ast->rules[0]->alternatives[0]->symbols);
        self::assertCount(0, $ast->rules[0]->alternatives[1]->symbols);
        self::assertCount(1, $ast->rules[0]->alternatives[2]->symbols);
    }

    public function testParseRuleWithPrecAndAction(): void
    {
        $input = <<<'BISON'
%%
rule: A %prec UMINUS { action_code };
BISON;

        $ast = (new BisonParser())->parse($input);

        $alt = $ast->rules[0]->alternatives[0];
        self::assertSame('UMINUS', $alt->prec);
        self::assertNotNull($alt->action);
        self::assertStringContainsString('action_code', $alt->action);
    }

    public function testParseRuleWithDprecAndMerge(): void
    {
        $input = <<<'BISON'
%%
rule: A %dprec 1 %merge <func>;
BISON;

        $ast = (new BisonParser())->parse($input);

        $alt = $ast->rules[0]->alternatives[0];
        self::assertSame(1, $alt->dprec);
        self::assertSame('func', $alt->merge);
    }

    public function testParseEpilogueMultipleTokensJoinedWithSpace(): void
    {
        $input = <<<'BISON'
%%
rule: A;
%%
token1 token2 token3
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertNotNull($ast->epilogue);
        self::assertSame('token1 token2 token3', $ast->epilogue);
    }

    public function testParseActionBeforePrec(): void
    {
        $input = <<<'BISON'
%%
rule: A { action_first } %prec TOKEN;
BISON;

        $ast = (new BisonParser())->parse($input);

        $alt = $ast->rules[0]->alternatives[0];
        self::assertNotNull($alt->action);
        self::assertSame('TOKEN', $alt->prec);
    }

    public function testParsePrecBeforeAction(): void
    {
        $input = <<<'BISON'
%%
rule: A %prec TOKEN { action_after };
BISON;

        $ast = (new BisonParser())->parse($input);

        $alt = $ast->rules[0]->alternatives[0];
        self::assertSame('TOKEN', $alt->prec);
        self::assertNotNull($alt->action);
        self::assertStringContainsString('action_after', $alt->action);
    }

    public function testParseRuleWithAllDirectives(): void
    {
        $input = <<<'BISON'
%%
rule: A %prec TOKEN %dprec 2 %merge <func> { action };
BISON;

        $ast = (new BisonParser())->parse($input);

        $alt = $ast->rules[0]->alternatives[0];
        self::assertSame('TOKEN', $alt->prec);
        self::assertSame(2, $alt->dprec);
        self::assertSame('func', $alt->merge);
        self::assertNotNull($alt->action);
        self::assertStringContainsString('action', $alt->action);
    }

    public function testParseMultipleRulesWithSameName(): void
    {
        $input = <<<'BISON'
%%
rule: A;
rule: B;
rule: C;
BISON;

        $ast = (new BisonParser())->parse($input);

        self::assertCount(3, $ast->rules);
        self::assertSame('rule', $ast->rules[0]->name);
        self::assertSame('rule', $ast->rules[1]->name);
        self::assertSame('rule', $ast->rules[2]->name);
        self::assertSame('A', $ast->rules[0]->alternatives[0]->symbols[0]->value);
        self::assertSame('B', $ast->rules[1]->alternatives[0]->symbols[0]->value);
        self::assertSame('C', $ast->rules[2]->alternatives[0]->symbols[0]->value);
    }
}
