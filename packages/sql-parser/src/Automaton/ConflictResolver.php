<?php

declare(strict_types=1);

namespace SqlParser\Automaton;

use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\Precedence;
use SqlParser\Grammar\PrecedencePolicy;
use SqlParser\Table\ActionCode;

/**
 * Settles the conflicts of one state the way its generator would.
 *
 * A shift/reduce conflict is decided by precedence when both the token and
 * the rule are ranked: the higher rank wins, and equal ranks follow the
 * token's associativity. Without ranks the shift wins and the conflict is
 * counted. A reduce/reduce conflict goes to the earlier rule, except that
 * Lemon lets a higher-ranked rule win first.
 *
 * @visibility root
 */
final class ConflictResolver
{
    /**
     * @param Grammar $grammar Grammar whose precedences decide
     */
    public function __construct(private readonly Grammar $grammar)
    {
    }

    /**
     * Resolves the actions of one state.
     *
     * @param array<int, int> $shifts Target state by terminal
     * @param array<int, array<int, int>> $lookaheads Lookahead bitset by completed rule
     *
     * @return ResolvedState The settled actions
     */
    public function resolve(array $shifts, array $lookaheads): ResolvedState
    {
        $actions = [];
        foreach ($shifts as $terminal => $target) {
            $actions[$terminal] = ActionCode::shift($target);
        }
        $counts = [];
        $alternatives = [];
        $shiftReduce = 0;
        $reduceReduce = 0;
        ksort($lookaheads);
        foreach ($lookaheads as $rule => $set) {
            $counts[$rule] = 0;
            foreach (Bitset::members($set) as $terminal) {
                $current = $actions[$terminal] ?? null;
                if ($current === null) {
                    $actions[$terminal] = ActionCode::reduce($rule);
                    $counts[$rule]++;
                } elseif (ActionCode::isShift($current)) {
                    $winner = $this->shiftOrReduce($terminal, $rule);
                    if ($winner === null) {
                        $shiftReduce++;
                        $alternatives[$terminal][] = ActionCode::reduce($rule);
                    } elseif ($winner === ActionCode::ERROR) {
                        $actions[$terminal] = ActionCode::ERROR;
                        unset($alternatives[$terminal]);
                    } elseif (ActionCode::isReduce($winner)) {
                        $actions[$terminal] = $winner;
                        $counts[$rule]++;
                    }
                } elseif (ActionCode::isReduce($current)) {
                    $winner = $this->reduceOrReduce(ActionCode::rule($current), $rule);
                    if ($winner === null) {
                        $reduceReduce++;
                        $alternatives[$terminal][] = ActionCode::reduce($rule);
                    } elseif ($winner === $rule) {
                        $counts[ActionCode::rule($current)]--;
                        $actions[$terminal] = ActionCode::reduce($rule);
                        $counts[$rule]++;
                    }
                }
            }
        }

        return new ResolvedState($actions, $counts, $shiftReduce, $reduceReduce, $alternatives);
    }

    /**
     * Decides a shift/reduce conflict on one terminal.
     *
     * @param int $terminal Terminal that can be shifted
     * @param int $rule Rule that can be reduced
     *
     * @return int|null The winning action code, the error code when neither may win, or null when the conflict stays unresolved
     */
    public function shiftOrReduce(int $terminal, int $rule): ?int
    {
        $token = $this->grammar->memberPrecedence($terminal);
        $ranked = $this->grammar->rulePrecedence($this->grammar->rules[$rule]);
        if ($token === null || $ranked === null) {
            return null;
        }
        if ($token->level !== $ranked->level) {
            return $token->level > $ranked->level ? ActionCode::shift(0) : ActionCode::reduce($rule);
        }

        return match ($token->associativity) {
            Associativity::Right => ActionCode::shift(0),
            Associativity::Left => ActionCode::reduce($rule),
            Associativity::NonAssoc => ActionCode::ERROR,
            Associativity::Precedence => null,
        };
    }

    /**
     * Decides a reduce/reduce conflict between two rules that reduce on the same terminal.
     *
     * Bison always keeps the earlier rule and counts the conflict. Lemon lets
     * the higher-ranked rule win outright and counts the conflict only when
     * the ranks are missing or equal, in which case the earlier rule wins.
     *
     * @param int $earlier Rule that currently holds the terminal
     * @param int $later Rule that also reduces on it
     *
     * @return int|null The winning rule, or null when the earlier rule wins only by default
     */
    public function reduceOrReduce(int $earlier, int $later): ?int
    {
        if ($this->grammar->policy !== PrecedencePolicy::FirstRankedTerminal) {
            return null;
        }
        $first = $this->grammar->rulePrecedence($this->grammar->rules[$earlier]);
        $second = $this->grammar->rulePrecedence($this->grammar->rules[$later]);
        if (!$first instanceof Precedence || !$second instanceof Precedence || $first->level === $second->level) {
            return null;
        }

        return $second->level > $first->level ? $later : $earlier;
    }
}
