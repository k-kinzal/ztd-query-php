<?php

declare(strict_types=1);

namespace Tests\Unit\Automaton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\Bitset;
use SqlParser\Automaton\BuildResult;
use SqlParser\Automaton\ClosureIndex;
use SqlParser\Automaton\ConflictResolver;
use SqlParser\Automaton\ConflictSummary;
use SqlParser\Automaton\Digraph;
use SqlParser\Automaton\LookaheadSets;
use SqlParser\Automaton\Lr0Automaton;
use SqlParser\Automaton\Lr0Builder;
use SqlParser\Automaton\NullableSet;
use SqlParser\Automaton\ParseTableBuilder;
use SqlParser\Automaton\ResolvedState;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\Precedence;
use SqlParser\Grammar\PrecedencePolicy;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;
use SqlParser\Table\ActionCode;
use SqlParser\Table\ArrayRows;
use SqlParser\Table\ParseTable;
use SqlParser\Table\TableRule;

#[CoversClass(ParseTableBuilder::class)]
#[UsesClass(ActionCode::class)]
#[UsesClass(ArrayRows::class)]
#[UsesClass(Bitset::class)]
#[UsesClass(BuildResult::class)]
#[UsesClass(ClosureIndex::class)]
#[UsesClass(ConflictResolver::class)]
#[UsesClass(ConflictSummary::class)]
#[UsesClass(Digraph::class)]
#[UsesClass(LookaheadSets::class)]
#[UsesClass(Lr0Automaton::class)]
#[UsesClass(Lr0Builder::class)]
#[UsesClass(NullableSet::class)]
#[UsesClass(ParseTable::class)]
#[UsesClass(ResolvedState::class)]
#[UsesClass(TableRule::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(GrammarBuilder::class)]
#[UsesClass(Precedence::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[Small]
final class ParseTableBuilderTest extends TestCase
{
    public function testBuildSettlesAnAmbiguousGrammarWithPrecedence(): void
    {
        $builder = new GrammarBuilder();
        $builder->precedence(['+'], Associativity::Left);
        $builder->precedence(['*'], Associativity::Left);
        $builder->terminal('NUM');
        $builder->rule('expr', ['expr', '+', 'expr']);
        $builder->rule('expr', ['expr', '*', 'expr']);
        $builder->rule('expr', ['NUM']);
        $result = (new ParseTableBuilder())->build($builder->build());
        $table = $result->table;
        $symbols = $table->symbols;
        $afterPlusExpr = $table->action($table->action($table->action(0, $symbols->id('expr') ?? -1), $symbols->id('+') ?? -1), $symbols->id('expr') ?? -1);

        self::assertTrue($result->conflicts->isExpected());
        self::assertSame(0, $result->conflicts->shiftReduce);
        self::assertSame(ActionCode::reduce(1), $table->action($afterPlusExpr, $symbols->id('+') ?? -1));
        self::assertTrue(ActionCode::isShift($table->action($afterPlusExpr, $symbols->id('*') ?? -1)));
        self::assertSame(ActionCode::ACCEPT, $table->action($table->action($table->action(0, $symbols->id('expr') ?? -1), 0), 0));
    }

    public function testBuildCountsConflictsItSettlesByDefault(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('+');
        $builder->terminal('NUM');
        $builder->rule('expr', ['expr', '+', 'expr']);
        $builder->rule('expr', ['NUM']);
        $result = (new ParseTableBuilder())->build($builder->build());

        self::assertSame(1, $result->conflicts->shiftReduce);
        self::assertFalse($result->conflicts->isExpected());
        self::assertGreaterThan(0, $result->stateCount);
    }

    public function testBuildMakesANonAssociativeOperatorAnError(): void
    {
        $builder = new GrammarBuilder();
        $builder->precedence(['='], Associativity::NonAssoc);
        $builder->terminal('NUM');
        $builder->rule('expr', ['expr', '=', 'expr']);
        $builder->rule('expr', ['NUM']);
        $table = (new ParseTableBuilder())->build($builder->build())->table;
        $symbols = $table->symbols;
        $afterEqualsExpr = $table->action($table->action($table->action(0, $symbols->id('expr') ?? -1), $symbols->id('=') ?? -1), $symbols->id('expr') ?? -1);

        self::assertSame(ActionCode::ERROR, $table->action($afterEqualsExpr, $symbols->id('=') ?? -1));
        self::assertSame(ActionCode::reduce(1), $table->action($afterEqualsExpr, 0));
    }

    public function testBuildKeepsReductionsExplicitWhereTheWildcardActs(): void
    {
        $builder = new GrammarBuilder();
        $builder->policy(PrecedencePolicy::FirstRankedTerminal);
        $builder->wildcard('ANY');
        $builder->terminal('LP');
        $builder->terminal('RP');
        $builder->rule('list', ['LP', 'any', 'RP']);
        $builder->rule('any', []);
        $builder->rule('any', ['any', 'ANY']);
        $table = (new ParseTableBuilder())->build($builder->build())->table;
        $symbols = $table->symbols;
        $inside = $table->action($table->action(0, $symbols->id('LP') ?? -1), $symbols->id('any') ?? -1);

        self::assertSame(ActionCode::ERROR, $table->defaults[$inside]);
        self::assertTrue(ActionCode::isShift($table->action($inside, $symbols->id('RP') ?? -1)));
        self::assertTrue(ActionCode::isShift($table->action($inside, $symbols->id('ANY') ?? -1)));
    }

    public function testBuildSpellsTokenClassesOutToTheirMembers(): void
    {
        $builder = new GrammarBuilder();
        $builder->policy(PrecedencePolicy::FirstRankedTerminal);
        $builder->tokenClass('id', ['ID', 'INDEXED']);
        $builder->rule('nm', ['id']);
        $table = (new ParseTableBuilder())->build($builder->build())->table;
        $symbols = $table->symbols;

        self::assertTrue(ActionCode::isShift($table->action(0, $symbols->id('INDEXED') ?? -1)));
        self::assertSame($table->action(0, $symbols->id('ID') ?? -1), $table->action(0, $symbols->id('INDEXED') ?? -1));
        self::assertSame(ActionCode::ERROR, $table->action(0, $symbols->id('id') ?? -1));
    }

    public function testDefaultRule(): void
    {
        $builder = new ParseTableBuilder();

        self::assertSame(0, $builder->defaultRule(new ResolvedState([], [], 0, 0), false, true));
        self::assertNull($builder->defaultRule(new ResolvedState([], [], 0, 0), true, false));
        self::assertSame(4, $builder->defaultRule(new ResolvedState([], [4 => 0], 0, 0), true, false));
        self::assertSame(2, $builder->defaultRule(new ResolvedState([], [1 => 1, 2 => 3, 3 => 3], 0, 0), false, false));
        self::assertNull($builder->defaultRule(new ResolvedState([], [1 => 0], 0, 0), false, false));
    }

    public function testWithoutDefault(): void
    {
        $builder = new ParseTableBuilder();
        $actions = [1 => ActionCode::reduce(3), 2 => ActionCode::shift(4), 3 => ActionCode::ERROR];

        self::assertSame([2 => ActionCode::shift(4), 3 => ActionCode::ERROR], $builder->withoutDefault($actions, 3));
        self::assertSame($actions, $builder->withoutDefault($actions, null));
    }

    public function testExpandShifts(): void
    {
        $symbols = new SymbolTable(['$end', 'ID', 'INDEXED', 'id'], ['$accept', 'nm']);
        $grammar = new Grammar($symbols, [new Rule(0, 4, [5, 0], 0)], [], PrecedencePolicy::FirstRankedTerminal, null, [3 => [1, 2]]);

        self::assertSame([1 => 7, 2 => 9], (new ParseTableBuilder())->expandShifts([1 => 7, 3 => 9], $grammar));
    }

    public function testExpandLookaheads(): void
    {
        $symbols = new SymbolTable(['$end', 'ID', 'INDEXED', 'id'], ['$accept', 'nm']);
        $grammar = new Grammar($symbols, [new Rule(0, 4, [5, 0], 0)], [], PrecedencePolicy::FirstRankedTerminal, null, [3 => [1, 2]]);
        $set = Bitset::empty(4);
        Bitset::add($set, 3);
        $expanded = (new ParseTableBuilder())->expandLookaheads([1 => $set], $grammar);

        self::assertSame([1, 2, 3], Bitset::members($expanded[1]));
        self::assertSame([1 => $set], (new ParseTableBuilder())->expandLookaheads([1 => $set], new Grammar($symbols, [new Rule(0, 4, [5, 0], 0)])));
    }
}
