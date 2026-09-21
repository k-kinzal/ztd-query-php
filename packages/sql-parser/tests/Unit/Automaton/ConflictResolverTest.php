<?php

declare(strict_types=1);

namespace Tests\Unit\Automaton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\Bitset;
use SqlParser\Automaton\ConflictResolver;
use SqlParser\Automaton\ResolvedState;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\Precedence;
use SqlParser\Grammar\PrecedencePolicy;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;
use SqlParser\Table\ActionCode;

#[CoversClass(ConflictResolver::class)]
#[UsesClass(ActionCode::class)]
#[UsesClass(Bitset::class)]
#[UsesClass(ResolvedState::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(Precedence::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class ConflictResolverTest extends TestCase
{
    public function testResolveKeepsPlainShiftsAndReductions(): void
    {
        $symbols = new SymbolTable(['$end', 'NUM', '+'], ['$accept', 'expr']);
        $grammar = new Grammar($symbols, [new Rule(0, 3, [4, 0], 0), new Rule(1, 4, [1], 0)]);
        $set = Bitset::empty(3);
        Bitset::add($set, 0);
        $resolved = (new ConflictResolver($grammar))->resolve([1 => 5], [1 => $set]);

        self::assertSame([1 => 5, 0 => ActionCode::reduce(1)], $resolved->actions);
        self::assertSame([1 => 1], $resolved->reductionCounts);
        self::assertSame(0, $resolved->shiftReduceConflicts);
    }

    public function testResolveCountsAnUnrankedShiftReduceConflictAndShifts(): void
    {
        $symbols = new SymbolTable(['$end', 'NUM', '+'], ['$accept', 'expr']);
        $grammar = new Grammar($symbols, [new Rule(0, 3, [4, 0], 0), new Rule(1, 4, [4, 2, 4], 0)]);
        $set = Bitset::empty(3);
        Bitset::add($set, 2);
        $resolved = (new ConflictResolver($grammar))->resolve([2 => 5], [1 => $set]);

        self::assertSame([2 => 5], $resolved->actions);
        self::assertSame(1, $resolved->shiftReduceConflicts);
        self::assertSame([2 => [ActionCode::reduce(1)]], $resolved->alternatives);
        self::assertSame([1 => 0], $resolved->reductionCounts);
    }

    public function testResolveCountsAReduceReduceConflictAndKeepsTheEarlierRule(): void
    {
        $symbols = new SymbolTable(['$end', 'NUM'], ['$accept', 'a', 'b']);
        $grammar = new Grammar($symbols, [new Rule(0, 2, [3, 0], 0), new Rule(1, 3, [1], 0), new Rule(2, 4, [1], 0)]);
        $set = Bitset::empty(2);
        Bitset::add($set, 0);
        $resolved = (new ConflictResolver($grammar))->resolve([], [2 => $set, 1 => $set]);

        self::assertSame([0 => ActionCode::reduce(1)], $resolved->actions);
        self::assertSame(1, $resolved->reduceReduceConflicts);
        self::assertSame([0 => [ActionCode::reduce(2)]], $resolved->alternatives);
    }

    public function testResolveLetsAHigherRankedLaterRuleWinUnderLemonsPolicy(): void
    {
        $symbols = new SymbolTable(['$end', 'NOT', 'IS'], ['$accept', 'expr']);
        $precedences = [1 => new Precedence(1, Associativity::Right), 2 => new Precedence(2, Associativity::Left)];
        $grammar = new Grammar($symbols, [new Rule(0, 3, [4, 0], 0), new Rule(1, 4, [1, 4], 0), new Rule(2, 4, [4, 2, 1, 4], 1)], $precedences, PrecedencePolicy::FirstRankedTerminal);
        $set = Bitset::empty(3);
        Bitset::add($set, 0);
        $resolved = (new ConflictResolver($grammar))->resolve([], [1 => $set, 2 => $set]);

        self::assertSame([0 => ActionCode::reduce(2)], $resolved->actions);
        self::assertSame(0, $resolved->reduceReduceConflicts);
        self::assertSame([1 => 0, 2 => 1], $resolved->reductionCounts);
    }

    public function testShiftOrReduce(): void
    {
        $symbols = new SymbolTable(['$end', 'NUM', '+', '*', '=', '^'], ['$accept', 'expr']);
        $precedences = [2 => new Precedence(1, Associativity::Left), 3 => new Precedence(2, Associativity::Left), 4 => new Precedence(3, Associativity::NonAssoc), 5 => new Precedence(4, Associativity::Right)];
        $rules = [new Rule(0, 6, [7, 0], 0), new Rule(1, 7, [7, 2, 7], 0), new Rule(2, 7, [7, 3, 7], 1), new Rule(3, 7, [7, 4, 7], 2), new Rule(4, 7, [7, 5, 7], 3), new Rule(5, 7, [1], 4)];
        $resolver = new ConflictResolver(new Grammar($symbols, $rules, $precedences));

        self::assertSame(ActionCode::reduce(1), $resolver->shiftOrReduce(2, 1));
        self::assertSame(ActionCode::shift(0), $resolver->shiftOrReduce(3, 1));
        self::assertSame(ActionCode::reduce(2), $resolver->shiftOrReduce(2, 2));
        self::assertSame(ActionCode::ERROR, $resolver->shiftOrReduce(4, 3));
        self::assertSame(ActionCode::shift(0), $resolver->shiftOrReduce(5, 4));
        self::assertNull($resolver->shiftOrReduce(2, 5));
    }

    public function testShiftOrReduceLeavesABareRankUnresolved(): void
    {
        $symbols = new SymbolTable(['$end', 'NUM', 'IF'], ['$accept', 'expr']);
        $grammar = new Grammar($symbols, [new Rule(0, 3, [4, 0], 0), new Rule(1, 4, [2, 4], 0)], [2 => new Precedence(1, Associativity::Precedence)]);

        self::assertNull((new ConflictResolver($grammar))->shiftOrReduce(2, 1));
    }

    public function testReduceOrReduce(): void
    {
        $symbols = new SymbolTable(['$end', 'A', 'B'], ['$accept', 'x']);
        $precedences = [1 => new Precedence(1, Associativity::Left), 2 => new Precedence(2, Associativity::Left)];
        $rules = [new Rule(0, 3, [4, 0], 0), new Rule(1, 4, [1], 0), new Rule(2, 4, [2], 1), new Rule(3, 4, [], 2)];
        $lemon = new ConflictResolver(new Grammar($symbols, $rules, $precedences, PrecedencePolicy::FirstRankedTerminal));
        $bison = new ConflictResolver(new Grammar($symbols, $rules, $precedences));

        self::assertSame(2, $lemon->reduceOrReduce(1, 2));
        self::assertSame(2, $lemon->reduceOrReduce(2, 1));
        self::assertNull($lemon->reduceOrReduce(1, 3));
        self::assertNull($lemon->reduceOrReduce(1, 1));
        self::assertNull($bison->reduceOrReduce(1, 2));
    }
}
