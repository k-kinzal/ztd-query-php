<?php

declare(strict_types=1);

namespace SqlParser\Automaton;

use SqlParser\Grammar\Grammar;
use SqlParser\Table\ActionCode;
use SqlParser\Table\ArrayRows;
use SqlParser\Table\ParseTable;
use SqlParser\Table\TableRule;

/**
 * Builds the LALR(1) parse table of a grammar.
 *
 * Each state keeps its shifts, its gotos and the reductions that could not
 * become its default. The default is the reduction with the most lookahead
 * terminals, and a state with one reduction and nothing to shift reduces
 * without looking at all. A state that acts on the wildcard keeps every
 * reduction explicit, as Lemon does, so the wildcard cannot swallow a token
 * the state would rather reduce on. Token classes are spelled out to their
 * members before conflicts are settled, as Lemon does, so a lexer never has
 * to know about them.
 *
 * @visibility root
 */
final class ParseTableBuilder
{
    /**
     * @param Lr0Builder $lr0 Builds the LR(0) automaton
     */
    public function __construct(private readonly Lr0Builder $lr0 = new Lr0Builder())
    {
    }

    /**
     * Builds the table.
     *
     * @param Grammar $grammar Grammar to build for
     *
     * @return BuildResult The table and the conflicts settled by default
     */
    public function build(Grammar $grammar): BuildResult
    {
        $automaton = $this->lr0->build($grammar);
        $lookaheads = new LookaheadSets($grammar, $automaton, new NullableSet($grammar));
        $resolver = new ConflictResolver($grammar);
        $symbols = $grammar->symbols;
        $rows = [];
        $defaults = [];
        $shiftReduce = 0;
        $reduceReduce = 0;
        for ($state = 0; $state < $automaton->stateCount(); $state++) {
            $shifts = [];
            $gotos = [];
            foreach ($automaton->transitions[$state] as $symbol => $target) {
                if ($symbols->isTerminal($symbol)) {
                    $shifts[$symbol] = $target;
                } else {
                    $gotos[$symbol] = ActionCode::shift($target);
                }
            }
            $resolved = $resolver->resolve($this->expandShifts($shifts, $grammar), $this->expandLookaheads($lookaheads->of($state), $grammar));
            $shiftReduce += $resolved->shiftReduceConflicts;
            $reduceReduce += $resolved->reduceReduceConflicts;
            $usesWildcard = $grammar->wildcard !== null && isset($resolved->actions[$grammar->wildcard]);
            $default = $usesWildcard ? null : $this->defaultRule($resolved, $shifts === [], in_array(0, $automaton->reductions[$state], true));
            $defaults[] = $default === null ? ActionCode::ERROR : ActionCode::reduce($default);
            $rows[] = $this->withoutDefault($resolved->actions, $default) + $gotos;
        }
        $rules = array_map(
            static fn ($rule): TableRule => new TableRule($rule->lhs, $rule->length(), $rule->ordinal, $rule->hidden),
            $grammar->rules,
        );
        $table = new ParseTable($symbols, $rules, $defaults, new ArrayRows($rows), $grammar->fallbacks, $grammar->wildcard);

        return new BuildResult($table, new ConflictSummary($shiftReduce, $reduceReduce, $grammar->expectedConflicts), $automaton->stateCount());
    }

    /**
     * Chooses the rule a state reduces by when no explicit action applies.
     *
     * @param ResolvedState $resolved The state's settled actions
     * @param bool $consistent Whether the state shifts nothing
     * @param bool $accepting Whether the state completes the augmented start rule
     *
     * @return int|null The rule, or null when the state has nothing to reduce
     */
    public function defaultRule(ResolvedState $resolved, bool $consistent, bool $accepting): ?int
    {
        if ($accepting) {
            return 0;
        }
        $counts = $resolved->reductionCounts;
        if ($counts === []) {
            return null;
        }
        if ($consistent && count($counts) === 1) {
            return array_key_first($counts);
        }
        $best = null;
        foreach ($counts as $rule => $count) {
            if ($count > 0 && ($best === null || $count > $counts[$best])) {
                $best = $rule;
            }
        }

        return $best;
    }

    /**
     * Drops the explicit reductions the default already covers.
     *
     * @param array<int, int> $actions Action code by terminal
     * @param int|null $default Rule the state reduces by default
     *
     * @return array<int, int> The remaining explicit actions
     */
    public function withoutDefault(array $actions, ?int $default): array
    {
        if ($default === null) {
            return $actions;
        }
        $code = ActionCode::reduce($default);

        return array_filter($actions, static fn (int $action): bool => $action !== $code);
    }

    /**
     * Replaces a shift on a token class by the same shift on every member.
     *
     * Lemon adds one shift per member, so a member shifted by two classes is a
     * shift/shift conflict it would reject; the first class keeps the member.
     *
     * @param array<int, int> $shifts Target state by terminal or token class
     * @param Grammar $grammar Grammar declaring the classes
     *
     * @return array<int, int> Target state by terminal
     */
    public function expandShifts(array $shifts, Grammar $grammar): array
    {
        foreach ($grammar->tokenClasses as $class => $members) {
            if (!isset($shifts[$class])) {
                continue;
            }
            foreach ($members as $member) {
                $shifts[$member] ??= $shifts[$class];
            }
            unset($shifts[$class]);
        }

        return $shifts;
    }

    /**
     * Replaces a token class in a lookahead set by its members.
     *
     * @param array<int, array<int, int>> $lookaheads Lookahead bitset by completed rule
     * @param Grammar $grammar Grammar declaring the classes
     *
     * @return array<int, array<int, int>> Lookaheads over terminals only
     */
    public function expandLookaheads(array $lookaheads, Grammar $grammar): array
    {
        if ($grammar->tokenClasses === []) {
            return $lookaheads;
        }
        foreach ($lookaheads as $rule => $set) {
            foreach ($grammar->tokenClasses as $class => $members) {
                if (!Bitset::has($set, $class)) {
                    continue;
                }
                foreach ($members as $member) {
                    Bitset::add($set, $member);
                }
            }
            $lookaheads[$rule] = $set;
        }

        return $lookaheads;
    }
}
