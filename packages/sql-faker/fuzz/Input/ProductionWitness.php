<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Input;

use Closure;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;

/**
 * Solves minimum witnesses with (uses target, non-empty) states and positive expansion costs.
 */
final class ProductionWitness
{
    /**
     * @var array<string, array<int, WitnessNode>>
     */
    private readonly array $baseline;

    /**
     * @param Closure(string): bool $supported
     */
    public function __construct(private readonly Grammar $grammar, private readonly Closure $supported)
    {
        $this->baseline = $this->settled('', -1, []);
    }

    /**
     * Finds a complete root derivation containing the requested alternative.
     */
    public function find(string $root, string $targetRule, int $targetOrdinal): ?WitnessNode
    {
        $best = [];
        foreach ($this->baseline as $name => $states) {
            foreach ($states as $state => $node) {
                $marked = isset($node->contains[$targetRule . "\0" . $targetOrdinal]) ? 2 : 0;
                $best[$name][$state | $marked] = $node;
            }
        }
        $best = $this->settled($targetRule, $targetOrdinal, $best);
        return $best[$root][3] ?? null;
    }

    /**
     * Relaxes costs from known witnesses; cached witnesses are reclassified for each target.
     *
     * @param array<string, array<int, WitnessNode>> $best
     * @return array<string, array<int, WitnessNode>>
     */
    public function settled(string $targetRule, int $targetOrdinal, array $best): array
    {
        do {
            $changed = false;
            foreach ($this->grammar->ruleMap as $name => $rule) {
                foreach ($rule->alternatives as $ordinal => $production) {
                    $target = $name === $targetRule && $ordinal === $targetOrdinal;
                    foreach ($this->sequences($production, $best, $target ? 2 : 0) as $state => [$cost, $children]) {
                        if (!isset($best[$name][$state]) || $cost + 1 < $best[$name][$state]->cost) {
                            $best[$name][$state] = new WitnessNode($name, $ordinal, $children, $cost + 1, $state);
                            $changed = true;
                        }
                    }
                }
            }
        } while ($changed);
        return $best;
    }

    /**
     * Combines child states, retaining a least-cost finite witness for each state.
     *
     * @param array<string, array<int, WitnessNode>> $best
     * @return array<int, array{int, list<WitnessNode>}>
     */
    public function sequences(Production $production, array $best, int $initial): array
    {
        $states = [$initial => [0, []]];
        foreach ($production->symbols as $symbol) {
            $children = [];
            if ($symbol instanceof Terminal) {
                if (!($this->supported)($symbol->value)) {
                    return [];
                }
                $children[1] = [0, []];
            } else {
                foreach ($best[$symbol->value()] ?? [] as $state => $node) {
                    $children[$state] = [$node->cost, [$node]];
                }
            }
            $combined = [];
            foreach ($states as $leftState => [$leftCost, $leftNodes]) {
                foreach ($children as $rightState => [$rightCost, $rightNodes]) {
                    $state = $leftState | $rightState;
                    $cost = $leftCost + $rightCost;
                    if (!isset($combined[$state]) || $cost < $combined[$state][0]) {
                        $combined[$state] = [$cost, [...$leftNodes, ...$rightNodes]];
                    }
                }
            }
            $states = $combined;
        }
        return $states;
    }
}
