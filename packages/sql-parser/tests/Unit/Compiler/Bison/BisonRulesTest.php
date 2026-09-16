<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Bison;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\Bison\BisonRules;
use SqlParser\Compiler\Bison\BisonScanner;
use SqlParser\Compiler\Bison\BisonToken;
use SqlParser\Compiler\Bison\BisonTokens;
use SqlParser\Compiler\CodeBlockReader;
use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Compiler\SourceReader;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;

#[CoversClass(BisonRules::class)]
#[UsesClass(BisonScanner::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokens::class)]
#[UsesClass(CodeBlockReader::class)]
#[UsesClass(GrammarSourceException::class)]
#[UsesClass(SourceReader::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(GrammarBuilder::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[Small]
final class BisonRulesTest extends TestCase
{
    public function testReadRecordsAlternativesModifiersAndMidRuleActions(): void
    {
        $source = "expr: expr '+' expr { \$\$ = 1; } | '-' expr %prec NEG | NUM { a(); } NUM | %empty ; ; list[l]: expr[e] ;";
        $tokens = new BisonTokens((new BisonScanner())->scan($source));
        $builder = new GrammarBuilder();
        $builder->terminal('NUM');
        $builder->terminal('NEG');
        (new BisonRules())->read($tokens, $builder);
        $grammar = $builder->build();
        $names = static fn (Rule $rule): array => array_map(static fn (int $id): string => $grammar->symbols->name($id), $rule->rhs);

        self::assertSame(['expr', '+', 'expr'], $names($grammar->rules[1]));
        self::assertSame(['-', 'expr'], $names($grammar->rules[2]));
        self::assertSame($grammar->symbols->id('NEG'), $grammar->rules[2]->precedenceSymbol);
        self::assertTrue($grammar->rules[3]->hidden);
        self::assertSame('$@1', $grammar->symbols->name($grammar->rules[3]->lhs));
        self::assertSame(['NUM', '$@1', 'NUM'], $names($grammar->rules[4]));
        self::assertSame([], $grammar->rules[5]->rhs);
        self::assertSame(['expr'], $names($grammar->rules[6]));
        self::assertSame('list', $grammar->symbols->name($grammar->rules[6]->lhs));
    }

    public function testReadAcceptsARuleThatOmitsItsSemicolon(): void
    {
        $tokens = new BisonTokens((new BisonScanner())->scan('a: b b: A'));
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        (new BisonRules())->read($tokens, $builder);
        $grammar = $builder->build();

        self::assertCount(3, $grammar->rules);
        self::assertSame('b', $grammar->symbols->name($grammar->rules[2]->lhs));
    }

    public function testReadRejectsAMissingColon(): void
    {
        $tokens = new BisonTokens((new BisonScanner())->scan('a b ;'));

        $this->expectException(GrammarSourceException::class);

        (new BisonRules())->read($tokens, new GrammarBuilder());
    }

    public function testAlternative(): void
    {
        $tokens = new BisonTokens((new BisonScanner())->scan('A %dprec 2 %merge <t> | B ;'));
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->terminal('B');
        $rules = new BisonRules();

        self::assertTrue($rules->alternative($tokens, $builder, 'x'));
        self::assertFalse($rules->alternative($tokens, $builder, 'x'));
        self::assertCount(3, $builder->build()->rules);
    }

    public function testAlternativeRejectsAMissingPrecedenceSymbol(): void
    {
        $tokens = new BisonTokens((new BisonScanner())->scan('A %prec ;'));
        $builder = new GrammarBuilder();
        $builder->terminal('A');

        $this->expectException(GrammarSourceException::class);

        (new BisonRules())->alternative($tokens, $builder, 'x');
    }

    public function testMidRule(): void
    {
        $builder = new GrammarBuilder();
        $rules = new BisonRules();

        self::assertSame('$@1', $rules->midRule($builder));
        self::assertSame('$@2', $rules->midRule($builder));
        self::assertTrue($builder->isNonterminal('$@2'));
    }

    public function testContinues(): void
    {
        $rules = new BisonRules();

        self::assertTrue($rules->continues(new BisonTokens((new BisonScanner())->scan('A'))));
        self::assertTrue($rules->continues(new BisonTokens((new BisonScanner())->scan('<t> { b }'))));
        self::assertFalse($rules->continues(new BisonTokens((new BisonScanner())->scan('| A'))));
        self::assertFalse($rules->continues(new BisonTokens((new BisonScanner())->scan('%prec X'))));
        self::assertFalse($rules->continues(new BisonTokens([])));
    }

    public function testStartsRule(): void
    {
        $rules = new BisonRules();

        self::assertTrue($rules->startsRule(new BisonTokens((new BisonScanner())->scan('a: b'))));
        self::assertTrue($rules->startsRule(new BisonTokens((new BisonScanner())->scan('a[x]: b'))));
        self::assertFalse($rules->startsRule(new BisonTokens((new BisonScanner())->scan('a b'))));
        self::assertFalse($rules->startsRule(new BisonTokens((new BisonScanner())->scan(': b'))));
    }

    public function testSkipNamedReference(): void
    {
        $tokens = new BisonTokens((new BisonScanner())->scan('[name] :'));
        (new BisonRules())->skipNamedReference($tokens);

        self::assertSame(':', $tokens->peek()?->text);
    }

    public function testSkipNamedReferenceRejectsAnUnclosedReference(): void
    {
        $tokens = new BisonTokens((new BisonScanner())->scan('[name :'));

        $this->expectException(GrammarSourceException::class);

        (new BisonRules())->skipNamedReference($tokens);
    }
}
