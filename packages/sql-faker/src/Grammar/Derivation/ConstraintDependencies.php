<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Derivation;

use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;

/**
 * Finds exactly which grammar rules can reach a still-constrained occurrence.
 */
final class ConstraintDependencies
{
    /**
     * @var array<string, array<string, true>>
     */
    private array $parents = [];
    /**
     * @var array<string, array<string, true>>
     */
    private array $cache = [];

    /**
     * Builds reverse grammar edges once, including recursive components.
     */
    public function __construct(Grammar $grammar)
    {
        foreach ($grammar->ruleMap as $name => $rule) {
            foreach ($rule->alternatives as $production) {
                foreach ($production->symbols as $symbol) {
                    if ($symbol instanceof NonTerminal) {
                        $this->parents[$symbol->value][$name] = true;
                    }
                }
            }
        }
    }

    /**
     * Caches by the remaining constraint targets, independently of their exact pattern or counter values.
     * @param GenerationPlan<bool> $plan
     * @param array<string, int> $occurrences
     * @return array<string, true>
     */
    public function affected(GenerationPlan $plan, array $occurrences): array
    {
        $targets = [];
        foreach ($plan->patternState($occurrences) as $name => $count) {
            if ($plan->patternAt($name, $count) !== null) {
                $targets[$name] = true;
            }
        }
        $key = serialize(array_keys($targets));
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $pending = array_keys($targets);
        while (($name = array_pop($pending)) !== null) {
            foreach ($this->parents[$name] ?? [] as $parent => $present) {
                if (!isset($targets[$parent])) {
                    $targets[$parent] = true;
                    $pending[] = $parent;
                }
            }
        }
        return $this->cache[$key] = $targets;
    }
}
