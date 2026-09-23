<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Seed;

use SqlFaker\Generation\Derivation\TerminationAnalyzer;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\Production;

/**
 * Answers how far each rule is from another rule, and which candidate a walk would take to finish soonest.
 *
 * @visibility namespace
 */
final class ProductionGraph
{
    private readonly TerminationAnalyzer $analyzer;

    /**
     * @var array<string, array<string, true>> Rules whose viable alternatives mention each rule
     */
    private readonly array $containers;

    /**
     * Indexes the grammar once; alternatives that can never finish are left out of every path.
     */
    public function __construct(Grammar $grammar)
    {
        $this->analyzer = new TerminationAnalyzer($grammar);
        $containers = [];
        foreach ($grammar->ruleMap as $name => $rule) {
            foreach ($rule->alternatives as $production) {
                if (!$this->analyzer->isProductionViable($production)) {
                    continue;
                }
                foreach ($production->nonTerminalNames() as $inner) {
                    $containers[$inner][$name] = true;
                }
            }
        }
        $this->containers = $containers;
    }

    /**
     * Counts the fewest expansions from every rule that can reach the named rule; rules that cannot are absent.
     *
     * @return array<string, int>
     */
    public function distances(string $rule): array
    {
        $distances = [$rule => 0];
        $queue = [$rule];
        for ($next = 0; isset($queue[$next]); ++$next) {
            $inner = $queue[$next];
            foreach (array_keys($this->containers[$inner] ?? []) as $outer) {
                if (!isset($distances[$outer])) {
                    $distances[$outer] = $distances[$inner] + 1;
                    $queue[] = $outer;
                }
            }
        }
        return $distances;
    }

    /**
     * Picks the candidate a walk past its depth would take: fewest expansions, then fewest tokens.
     *
     * @param non-empty-list<Production> $candidates
     */
    public function cheapest(array $candidates): int
    {
        $selected = 0;
        $fewestSteps = PHP_INT_MAX;
        $fewestTokens = PHP_INT_MAX;
        foreach ($candidates as $index => $candidate) {
            $steps = $this->analyzer->estimateProductionSteps($candidate);
            $tokens = $this->analyzer->estimateProductionLength($candidate);
            if ($steps < $fewestSteps || ($steps === $fewestSteps && $tokens < $fewestTokens)) {
                $fewestSteps = $steps;
                $fewestTokens = $tokens;
                $selected = $index;
            }
        }
        return $selected;
    }
}
