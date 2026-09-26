<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Plan\Compilation;

use SqlFaker\Generation\Plan\LexemeConstraint;
use SqlFaker\Generation\Plan\RulePlan;

/**
 * Carries the nearest declarations while descending through a planned subtree.
 */
final class Scope
{
    /**
     * @param array<string, RulePlan> $rules
     * @param array<string, LexemeConstraint> $lexemes
     */
    public function __construct(public readonly array $rules = [], public readonly array $lexemes = [])
    {
    }

    /**
     * More specific subtree declarations shadow inherited defaults.
     */
    public function enter(RulePlan $plan): self
    {
        $rules = array_replace($this->rules, $plan->rules);
        $lexemes = array_replace($this->lexemes, $plan->lexemes);
        ksort($rules);
        ksort($lexemes);
        return new self($rules, $lexemes);
    }
}
