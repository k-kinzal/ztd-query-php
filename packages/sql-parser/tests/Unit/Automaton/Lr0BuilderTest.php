<?php

declare(strict_types=1);

namespace Tests\Unit\Automaton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\ClosureIndex;
use SqlParser\Automaton\Lr0Automaton;
use SqlParser\Automaton\Lr0Builder;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;

#[CoversClass(Lr0Builder::class)]
#[UsesClass(ClosureIndex::class)]
#[UsesClass(Lr0Automaton::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(GrammarBuilder::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[Small]
final class Lr0BuilderTest extends TestCase
{
    public function testBuildDiscoversEveryStateOfTheTextbookGrammar(): void
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
        $symbols = $grammar->symbols;

        self::assertSame(13, $automaton->stateCount());
        self::assertSame([0], $automaton->kernels[0]);
        self::assertNotNull($automaton->transition(0, $symbols->id('E') ?? -1));
        self::assertNotNull($automaton->transition(0, $symbols->id('id') ?? -1));
        self::assertSame([6], $automaton->reductions[$automaton->transition(0, $symbols->id('id') ?? -1)]);
    }

    public function testBuildCompletesEmptyRulesInTheClosure(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->rule('s', ['opt', 'A']);
        $builder->rule('opt', []);
        $builder->rule('opt', ['A']);
        $grammar = $builder->build();
        $automaton = (new Lr0Builder())->build($grammar);

        self::assertSame([2], $automaton->reductions[0]);
        self::assertSame([], $automaton->transitions[$automaton->transition($automaton->transition(0, $grammar->symbols->id('opt') ?? -1) ?? -1, $grammar->symbols->id('A') ?? -1) ?? -1]);
    }
}
