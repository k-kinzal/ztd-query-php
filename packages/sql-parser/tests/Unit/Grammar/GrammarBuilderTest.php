<?php

declare(strict_types=1);

namespace Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\GrammarException;
use SqlParser\Grammar\Precedence;
use SqlParser\Grammar\PrecedencePolicy;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;
use SqlParser\Grammar\UnknownSymbolException;

#[CoversClass(GrammarBuilder::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(GrammarException::class)]
#[UsesClass(Precedence::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[UsesClass(UnknownSymbolException::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class GrammarBuilderTest extends TestCase
{
    public function testTerminal(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('NUM');

        self::assertTrue($builder->isTerminal('NUM'));
        self::assertFalse($builder->isNonterminal('NUM'));
    }

    public function testNonterminal(): void
    {
        $builder = new GrammarBuilder();
        $builder->nonterminal('expr');

        self::assertTrue($builder->isNonterminal('expr'));
        self::assertFalse($builder->isTerminal('expr'));
    }

    public function testIsTerminalCountsTokenClasses(): void
    {
        $builder = new GrammarBuilder();
        $builder->tokenClass('id', ['ID', 'INDEXED']);

        self::assertTrue($builder->isTerminal('id'));
        self::assertTrue($builder->isTerminal('INDEXED'));
    }

    public function testIsNonterminal(): void
    {
        $builder = new GrammarBuilder();
        $builder->rule('expr', ['NUM']);

        self::assertTrue($builder->isNonterminal('expr'));
    }

    public function testRule(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('NUM');
        $builder->terminal('+');
        $builder->rule('expr', ['expr', '+', 'expr']);
        $builder->rule('expr', ['NUM']);
        $grammar = $builder->build();

        self::assertSame(['$end', 'NUM', '+'], $grammar->symbols->terminals());
        self::assertSame(['$accept', 'expr'], $grammar->symbols->nonterminals());
        self::assertSame([4, 2, 4], $grammar->rules[1]->rhs);
        self::assertSame(1, $grammar->rules[2]->ordinal);
    }

    public function testPrecedence(): void
    {
        $builder = new GrammarBuilder();
        $builder->precedence(['+', '-'], Associativity::Left);
        $builder->precedence(['*'], Associativity::Right);
        $builder->rule('expr', ['expr', '+', 'expr']);
        $grammar = $builder->build();

        self::assertSame(1, $grammar->precedenceOf($grammar->symbols->id('-') ?? -1)?->level);
        self::assertSame(2, $grammar->precedenceOf($grammar->symbols->id('*') ?? -1)?->level);
        self::assertSame(Associativity::Right, $grammar->precedenceOf($grammar->symbols->id('*') ?? -1)->associativity);
    }

    public function testStart(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->rule('first', ['A']);
        $builder->rule('second', ['first']);
        $builder->start('second');

        self::assertSame('second', $builder->build()->symbols->name($builder->build()->startSymbol()));
    }

    public function testExpect(): void
    {
        $builder = new GrammarBuilder();
        $builder->expect(59);
        $builder->rule('s', []);

        self::assertSame(59, $builder->build()->expectedConflicts);
    }

    public function testPolicy(): void
    {
        $builder = new GrammarBuilder();
        $builder->policy(PrecedencePolicy::FirstRankedTerminal);
        $builder->rule('s', []);

        self::assertSame(PrecedencePolicy::FirstRankedTerminal, $builder->build()->policy);
    }

    public function testTokenClass(): void
    {
        $builder = new GrammarBuilder();
        $builder->tokenClass('id', ['ID', 'INDEXED']);
        $builder->rule('nm', ['id']);
        $grammar = $builder->build();
        $class = $grammar->symbols->id('id') ?? -1;

        self::assertTrue($grammar->symbols->isTerminal($class));
        self::assertSame([$grammar->symbols->id('ID'), $grammar->symbols->id('INDEXED')], $grammar->tokenClasses[$class]);
    }

    public function testFallback(): void
    {
        $builder = new GrammarBuilder();
        $builder->fallback('ID', ['ABORT', 'AFTER']);
        $builder->rule('nm', ['ID']);
        $grammar = $builder->build();

        self::assertSame($grammar->symbols->id('ID'), $grammar->fallbacks[$grammar->symbols->id('ABORT') ?? -1]);
        self::assertCount(2, $grammar->fallbacks);
    }

    public function testWildcard(): void
    {
        $builder = new GrammarBuilder();
        $builder->wildcard('ANY');
        $builder->rule('any', ['ANY']);
        $grammar = $builder->build();

        self::assertSame($grammar->symbols->id('ANY'), $grammar->wildcard);
    }

    public function testBuildAugmentsTheGrammar(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->rule('s', ['A']);
        $grammar = $builder->build();

        self::assertSame('$accept', $grammar->symbols->name($grammar->rules[0]->lhs));
        self::assertSame([$grammar->symbols->id('s'), 0], $grammar->rules[0]->rhs);
        self::assertNull($grammar->wildcard);
        self::assertSame([], $grammar->fallbacks);
    }

    public function testBuildNumbersHiddenRulesWithoutOrdinals(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->rule('$@1', [], null, true);
        $builder->rule('s', ['A', '$@1', 'A']);
        $builder->rule('s', ['A']);
        $grammar = $builder->build();

        self::assertTrue($grammar->rules[1]->hidden);
        self::assertSame(0, $grammar->rules[1]->ordinal);
        self::assertSame(0, $grammar->rules[2]->ordinal);
        self::assertSame(1, $grammar->rules[3]->ordinal);
    }

    public function testFirstVisibleLhs(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->rule('$@1', [], null, true);
        $builder->rule('s', ['A', '$@1', 'A']);

        self::assertSame('s', $builder->firstVisibleLhs());
        self::assertSame('s', $builder->build()->symbols->name($builder->build()->startSymbol()));
    }

    public function testBuildResolvesThePrecedenceSymbolOfARule(): void
    {
        $builder = new GrammarBuilder();
        $builder->precedence(['NEG'], Associativity::Right);
        $builder->terminal('-');
        $builder->rule('expr', ['-', 'expr'], 'NEG');

        self::assertSame($builder->build()->symbols->id('NEG'), $builder->build()->rules[1]->precedenceSymbol);
    }

    public function testBuildRejectsAnEmptyGrammar(): void
    {
        $this->expectException(GrammarException::class);

        (new GrammarBuilder())->build();
    }

    public function testBuildRejectsANameThatIsBothTerminalAndNonterminal(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('x');
        $builder->rule('x', []);

        $this->expectException(GrammarException::class);

        $builder->build();
    }

    public function testBuildRejectsAnUndeclaredSymbol(): void
    {
        $builder = new GrammarBuilder();
        $builder->rule('s', ['missing']);

        $this->expectException(UnknownSymbolException::class);

        $builder->build();
    }

    public function testNumberedRules(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->rule('s', ['A']);
        $builder->rule('s', []);
        $rules = $builder->numberedRules(new SymbolTable(['$end', 'A'], ['$accept', 's']));

        self::assertSame([1, 2], [$rules[0]->index, $rules[1]->index]);
        self::assertSame([0, 1], [$rules[0]->ordinal, $rules[1]->ordinal]);
        self::assertSame([1], $rules[0]->rhs);
    }

    public function testId(): void
    {
        $builder = new GrammarBuilder();
        $symbols = new SymbolTable(['$end', 'A'], ['$accept']);

        self::assertSame(1, $builder->id($symbols, 'A'));
    }

    public function testIdRejectsAnUnknownName(): void
    {
        $this->expectException(UnknownSymbolException::class);

        (new GrammarBuilder())->id(new SymbolTable(['$end'], ['$accept']), 'B');
    }
}
