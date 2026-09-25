<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Derivation;

use Closure;
use Faker\Generator;
use LogicException;
use SqlFaker\Generation\Plan\Compilation\GrammarCompiler;
use SqlFaker\Generation\Plan\Compilation\PreparedGrammar;
use SqlFaker\Generation\Plan\Compilation\ScopedGeneration;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\RulePlan;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;

/**
 * Derives grammar terminals without choosing spellings or pruning unsupported lexemes.
 */
final class TokenGenerator
{
    private readonly TerminationAnalyzer $termination;

    private readonly CompletionCosts $completion;

    /** @var array<string, RulePlan> */
    private array $plannedRules = [];
    private ?string $plannedRoot = null;
    private ?PreparedGrammar $prepared = null;
    private ?self $planned = null;

    /**
     * Lexical scopes belonging only to the latest derivation.
     */
    public ?ScopedGeneration $lastScope = null;

    /**
     * Fixes grammar and production choices; marker metadata affects emptiness, never handler availability.
     * @param Closure(string): bool $nonOutput
     */
    public function __construct(private readonly Grammar $grammar, private readonly Generator $faker, private readonly Closure $nonOutput)
    {
        $this->termination = new TerminationAnalyzer($grammar);
        $this->completion = new CompletionCosts($grammar, $nonOutput);
    }

    /**
     * Produces the selected terminal occurrences and their grammar provenance.
     * @param GenerationPlan<bool> $plan
     * @param (Closure(int, non-empty-list<Production>): ?int)|null $choose
     * @throws \SqlFaker\Generation\Exception\GenerationException When the grammar cannot complete the plan
     * @throws LogicException When derivation failed to retain its trace
     */
    public function generate(string $root, GenerationPlan $plan, ?Closure $choose = null): TerminalSequence
    {
        $this->lastScope = null;
        $this->prepare($root, $plan);
        if ($plan->rules() !== [] && $this->planned !== null && $this->prepared !== null) {
            $sequence = $this->planned->derive($this->prepared->grammar->startSymbol, $plan, $choose);
            $this->lastScope = $this->prepared->restore($sequence);
            return $this->lastScope->sequence;
        }
        return $this->derive($root, $plan, $choose);
    }

    /**
     * Runs the shared derivation engine on the selected grammar.
     * @throws \SqlFaker\Generation\Exception\GenerationException When the grammar cannot complete the plan
     * @param GenerationPlan<bool> $plan
     * @param (Closure(int, non-empty-list<Production>): ?int)|null $choose
     * @throws LogicException When derivation failed to retain its trace
     */
    public function derive(string $root, GenerationPlan $plan, ?Closure $choose = null): TerminalSequence
    {
        $derivation = new Derivation($this->grammar, $this->faker, $this->termination, $this->completion, $choose);
        $derivation->of($root, $plan);
        return ($derivation->trace ?? throw new LogicException('Missing derivation trace.'))->terminals();
    }

    /**
     * Uses the very same specialized grammar when choosing a feasible byte-plan budget.
     * @param GenerationPlan<bool> $plan
     */
    public function minimumExpansions(string $root, GenerationPlan $plan): int
    {
        $this->prepare($root, $plan);
        $generator = $plan->rules() !== [] && $this->planned !== null ? $this->planned : $this;
        $root = $plan->rules() !== [] && $this->prepared !== null ? $this->prepared->grammar->startSymbol : $root;
        return (new ConstrainedCompletion($generator->grammar, $generator->completion))->minimum(
            [new NonTerminal($root)],
            $plan,
            [],
            $plan->requiresNonEmpty(),
            $plan->expansionBudget() ?? 5000,
        );
    }

    /**
     * Keeps one preparation, avoiding unbounded caches as fuzz inputs change their constraints.
     * @param GenerationPlan<bool> $plan
     */
    public function prepare(string $root, GenerationPlan $plan): void
    {
        if ($plan->rules() === [] || ($root === $this->plannedRoot && $plan->rules() === $this->plannedRules)) {
            return;
        }
        $prepared = (new GrammarCompiler($this->grammar))->compile($root, $plan->rules());
        $this->planned = new self($prepared->grammar, $this->faker, $this->nonOutput);
        $this->prepared = $prepared;
        $this->plannedRoot = $root;
        $this->plannedRules = $plan->rules();
    }
}
