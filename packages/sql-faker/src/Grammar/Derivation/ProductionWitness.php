<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Derivation;

use Closure;
use LogicException;
use SqlFaker\Grammar\Choice\ByteChoices;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;

/**
 * Solves minimum witnesses with (uses target, non-empty) states and positive expansion costs.
 */
final class ProductionWitness
{
    private readonly CompletionCosts $costs;

    /**
     * @var array<string, array<int, WitnessNode>>
     */
    private readonly array $baseline;

    /**
     * @param Closure(string): bool $supported
     * @param (Closure(string): list<string>)|null $spellings
     */
    public function __construct(private readonly Grammar $grammar, private readonly Closure $supported, ?Closure $spellings = null)
    {
        $this->costs = new CompletionCosts($grammar, $supported, $spellings);
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
                foreach ($this->costs->sequence([$symbol]) as $state => $cost) {
                    if ($cost !== PHP_INT_MAX) {
                        $children[$state] = [0, []];
                    }
                }
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

    /**
     * Encodes one complete minimum witness, including its siblings.
     *
     * @throws LogicException When a witness disagrees with the actual candidate policy
     */
    public function encode(WitnessNode $witness): string
    {
        $form = [new NonTerminal($witness->rule)];
        $input = pack('V', $witness->cost - $this->costs->rule($witness->rule, true));
        $steps = 0;
        foreach ($witness->sequence() as [$name, $ordinal]) {
            $index = null;
            foreach ($form as $position => $symbol) {
                if ($symbol instanceof NonTerminal) {
                    $index = $position;
                    break;
                }
            }
            if ($index === null || $form[$index]->value() !== $name) {
                throw new LogicException('Witness expansion order differs from the sentential form.');
            }
            $remainder = array_slice($form, $index + 1);
            ++$steps;
            $candidates = $this->costs->affordable(
                $this->grammar->ruleMap[$name]->alternatives,
                $remainder,
                $this->costs->sequence(array_slice($form, 0, $index))[1] === PHP_INT_MAX,
                $witness->cost - $steps
            );
            $production = $this->grammar->ruleMap[$name]->alternatives[$ordinal];
            $choice = array_search($production, $candidates, true);
            if ($choice === false) {
                throw new LogicException('Witness production is not affordable after candidate filtering.');
            }
            $width = ByteChoices::width(count($candidates));
            for ($byte = 0; $byte < $width; ++$byte) {
                $input .= chr($choice % 256) . "\0";
                $choice = intdiv($choice, 256);
            }
            array_splice($form, $index, 1, $production->symbols);
        }
        return $input;
    }
}
