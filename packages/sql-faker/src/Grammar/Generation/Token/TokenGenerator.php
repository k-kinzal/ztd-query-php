<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Token;

use Closure;
use Faker\Generator;
use LogicException;
use SqlFaker\Grammar\Derivation\CompletionCosts;
use SqlFaker\Grammar\Derivation\Derivation;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\TerminationAnalyzer;
use SqlFaker\Grammar\Grammar;

/**
 * Derives grammar terminals without choosing spellings or pruning unsupported lexemes.
 */
final class TokenGenerator
{
    private readonly TerminationAnalyzer $termination;

    private readonly CompletionCosts $completion;

    /**
     * Fixes grammar and production choices; marker metadata affects emptiness, never handler availability.
     * @param Closure(string): bool $nonOutput
     */
    public function __construct(private readonly Grammar $grammar, private readonly Generator $faker, Closure $nonOutput)
    {
        $this->termination = new TerminationAnalyzer($grammar);
        $this->completion = new CompletionCosts($grammar, $nonOutput);
    }

    /**
     * Produces the selected terminal occurrences and their grammar provenance.
     * @param GenerationPlan<bool> $plan
     * @param (Closure(int): ?int)|null $choose
     * @throws \SqlFaker\Grammar\GenerationException When the grammar cannot complete the plan
     * @throws LogicException When derivation failed to retain its trace
     */
    public function generate(string $root, GenerationPlan $plan, ?Closure $choose = null): TerminalSequence
    {
        $derivation = new Derivation($this->grammar, $this->faker, $this->termination, $this->completion, $choose);
        $derivation->of($root, $plan);
        return ($derivation->trace ?? throw new LogicException('Missing derivation trace.'))->terminals();
    }
}
