<?php

declare(strict_types=1);

namespace Tests\Unit\Automaton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\Bitset;
use SqlParser\Automaton\ClosureIndex;
use SqlParser\Automaton\Digraph;
use SqlParser\Automaton\LookaheadSets;
use SqlParser\Automaton\Lr0Automaton;
use SqlParser\Automaton\Lr0Builder;
use SqlParser\Automaton\NullableSet;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;

#[CoversClass(LookaheadSets::class)]
#[UsesClass(Bitset::class)]
#[UsesClass(ClosureIndex::class)]
#[UsesClass(Digraph::class)]
#[UsesClass(Lr0Automaton::class)]
#[UsesClass(Lr0Builder::class)]
#[UsesClass(NullableSet::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(GrammarBuilder::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[Small]
final class LookaheadSetsTest extends TestCase
{
    public function testOfFollowsTheTextbookGrammar(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('id');
        $builder->terminal('+');
        $builder->terminal('*');
        $builder->terminal('(');
        $builder->terminal(')');
        $builder->rule('E', ['E', '+', 'T']);
        $builder->rule('E', ['T']);
        $builder->rule('T', ['T', '*', 'F']);
        $builder->rule('T', ['F']);
        $builder->rule('F', ['(', 'E', ')']);
        $builder->rule('F', ['id']);
        $grammar = $builder->build();
        $automaton = (new Lr0Builder())->build($grammar);
        $lookaheads = new LookaheadSets($grammar, $automaton, new NullableSet($grammar));
        $symbols = $grammar->symbols;
        $afterId = $automaton->transition(0, $symbols->id('id') ?? -1) ?? -1;
        $afterT = $automaton->transition(0, $symbols->id('T') ?? -1) ?? -1;

        self::assertSame([0, $symbols->id('+'), $symbols->id('*'), $symbols->id(')')], Bitset::members($lookaheads->of($afterId)[6]));
        self::assertSame([0, $symbols->id('+'), $symbols->id(')')], Bitset::members($lookaheads->of($afterT)[2]));
        self::assertSame([], $lookaheads->of(99));
    }

    public function testReads(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->terminal('B');
        $builder->rule('s', ['x', 'opt', 'B']);
        $builder->rule('x', ['A']);
        $builder->rule('opt', []);
        $grammar = $builder->build();
        $automaton = (new Lr0Builder())->build($grammar);
        $afterX = $automaton->transition(0, $grammar->symbols->id('x') ?? -1) ?? -1;
        $nodes = [0 => [$grammar->symbols->id('s') ?? -1 => 0, $grammar->symbols->id('x') ?? -1 => 1], $afterX => [$grammar->symbols->id('opt') ?? -1 => 2]];
        [$sets, $reads] = (new LookaheadSets($grammar, $automaton, new NullableSet($grammar)))->reads($automaton, $nodes, $grammar->symbols->terminalCount(), new NullableSet($grammar));

        self::assertSame([0], Bitset::members($sets[0]));
        self::assertSame([], Bitset::members($sets[1]));
        self::assertSame([1 => [2]], $reads);
        self::assertSame([$grammar->symbols->id('B')], Bitset::members($sets[2]));
    }

    public function testOfReadsThroughNullableNonterminals(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->terminal('B');
        $builder->rule('s', ['x', 'opt', 'B']);
        $builder->rule('x', ['A']);
        $builder->rule('opt', []);
        $builder->rule('opt', ['A']);
        $grammar = $builder->build();
        $automaton = (new Lr0Builder())->build($grammar);
        $lookaheads = new LookaheadSets($grammar, $automaton, new NullableSet($grammar));
        $afterA = $automaton->transition(0, $grammar->symbols->id('A') ?? -1) ?? -1;

        self::assertSame([$grammar->symbols->id('A'), $grammar->symbols->id('B')], Bitset::members($lookaheads->of($afterA)[2]));
    }
}
