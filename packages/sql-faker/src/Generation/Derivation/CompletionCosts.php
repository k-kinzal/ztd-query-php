<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Derivation;

use Closure;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Symbol;
use SqlFaker\Grammar\Model\Terminal;

/**
 * Computes minimum expansion costs separately for empty and non-empty output.
 *
 * @visibility root
 */
final class CompletionCosts
{
    /**
     * @var array<string, array{int, int}>
     */
    private array $costs;

    /**
     * @var array<string, array{int, int}>
     */
    private array $terminalCosts = [];

    /**
     * Computes the least fixed point once for a grammar and its declared non-output markers.
     *
     * @param Closure(string): bool $nonOutput Identifies declared non-output parser markers
     */
    public function __construct(Grammar $grammar, private readonly Closure $nonOutput)
    {
        $this->costs = array_fill_keys(array_keys($grammar->ruleMap), [PHP_INT_MAX, PHP_INT_MAX]);
        do {
            $changed = false;
            foreach ($grammar->ruleMap as $name => $rule) {
                foreach ($rule->alternatives as $production) {
                    $costs = $this->sequence($production->symbols);
                    foreach ([0, 1] as $state) {
                        $cost = self::add(1, $costs[$state]);
                        if ($cost < $this->costs[$name][$state]) {
                            $this->costs[$name][$state] = $cost;
                            $changed = true;
                        }
                    }
                }
            }
        } while ($changed);
    }

    /**
     * Returns the minimum total expansions from a rule.
     */
    public function rule(string $name, bool $nonEmpty): int
    {
        $costs = $this->costs[$name] ?? [PHP_INT_MAX, PHP_INT_MAX];
        return $nonEmpty ? $costs[1] : min($costs);
    }

    /**
     * Costs all children and pending siblings together, preserving nullability.
     *
     * @param list<Symbol> $symbols
     * @return array{int, int}
     */
    public function sequence(array $symbols): array
    {
        $costs = [0, PHP_INT_MAX];
        foreach ($symbols as $symbol) {
            if ($symbol instanceof Terminal) {
                if (!isset($this->terminalCosts[$symbol->value])) {
                    $empty = ($this->nonOutput)($symbol->value);
                    $child = [$empty ? 0 : PHP_INT_MAX, $empty ? PHP_INT_MAX : 0];
                    $this->terminalCosts[$symbol->value] = $child;
                }
                $child = $this->terminalCosts[$symbol->value];
            } else {
                $child = $this->costs[$symbol->value()] ?? [PHP_INT_MAX, PHP_INT_MAX];
            }
            $costs = self::combine($costs, $child);
        }
        return $costs;
    }

    /**
     * Costs the remainder while retaining the final non-empty requirement.
     *
     * @param list<Symbol> $remainder
     */
    public function completion(Production $production, array $remainder, bool $nonEmpty): int
    {
        $costs = self::combine($this->sequence($production->symbols), $this->sequence($remainder));
        return $nonEmpty ? $costs[1] : min($costs);
    }

    /**
     * Applies the candidate policy shared by token generation and planning.
     *
     * @param list<Production> $alternatives
     * @param list<Symbol> $remainder
     * @return list<Production>
     */
    public function affordable(array $alternatives, array $remainder, bool $nonEmpty, int $budget): array
    {
        $tail = $this->sequence($remainder);
        $affordable = [];
        foreach ($alternatives as $production) {
            $costs = self::combine($this->sequence($production->symbols), $tail);
            if (($nonEmpty ? $costs[1] : min($costs)) <= $budget) {
                $affordable[] = $production;
            }
        }
        return $affordable;
    }

    /**
     * Combines independent prefix and suffix costs, including their empty-output alternatives.
     * @param array{int, int} $left
     * @param array{int, int} $right
     * @return array{int, int}
     */
    public static function combine(array $left, array $right): array
    {
        return [self::add($left[0], $right[0]), min(
            self::add($left[1], min($right)),
            self::add($left[0], $right[1]),
        )];
    }

    /**
     * Stops once the already-derived prefix contains an emitting terminal; pending rules are not output.
     * @param list<Symbol> $symbols
     */
    public function hasTerminalOutput(array $symbols): bool
    {
        foreach ($symbols as $symbol) {
            if ($symbol instanceof Terminal && $this->sequence([$symbol])[1] === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Adds finite costs without overflowing the unreachable sentinel.
     */
    public static function add(int $left, int $right): int
    {
        return $left > PHP_INT_MAX - $right ? PHP_INT_MAX : $left + $right;
    }
}
