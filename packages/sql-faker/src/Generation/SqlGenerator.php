<?php

declare(strict_types=1);

namespace SqlFaker\Generation;

use Closure;
use Faker\Generator;
use SqlFaker\Coverage\GeneratorRevision;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Coverage\GrammarCoverageInventory;
use SqlFaker\Coverage\SequenceObservation;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\PlanBuilder;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Output\SqlSerializer;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Generation\Token\TokenGenerator;
use SqlFaker\Grammar\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalGrammar;

/**
 * Derives terminals, rewrites structural constraints, and realizes lexemes once.
 * Dialect definitions supply the syntax and boundary rules; no completed SQL is retried or repaired.
 *
 * @visibility root
 */
final class SqlGenerator
{
    private ?TokenGenerator $tokens = null;

    /**
     * The latest terminal sequence retains both the original grammar choices and their rewrites.
     */
    public ?TerminalSequence $lastSequence = null;

    /**
     * Selected lexical candidates and resolved boundary rules from the latest generation.
     */
    public ?ResolvedOutput $lastOutput = null;

    /**
     * Binds grammar, lexical definitions and structural rules before generation starts.
     * @param (Closure(string|null): string)|null $startSymbol Resolves explicitly requested release aliases
     */
    public function __construct(
        private readonly Grammar $grammar,
        private readonly Generator $faker,
        private readonly LexicalGrammar $lexicalGrammar,
        private readonly ?TokenRewriter $rewriter = null,
        private readonly ?Closure $startSymbol = null,
        private readonly ?GrammarCoverage $coverage = null,
        ?Grammar $original = null,
    ) {
        $coverage?->register(new GrammarCoverageInventory($grammar, $grammar->startSymbol, $lexicalGrammar->version(), $original), GeneratorRevision::current());
    }

    /**
     * Reuses the same grammar, rewrite rules and lexical definitions to compile plans.
     */
    public function planner(): PlanBuilder
    {
        return new PlanBuilder($this->grammar, $this->lexicalGrammar, $this->rewriter, $this->startSymbol);
    }

    /**
     * Generates once through the declared stages; candidate absence remains an error.
     * @template TRequiresNonEmpty of bool
     * @param GenerationPlan<TRequiresNonEmpty> $plan
     * @return (TRequiresNonEmpty is true ? non-empty-string : string)
     * @throws GenerationException When the grammar or plan cannot produce the requested output
     * @throws LexicalException When no applicable lexical realization exists
     */
    public function generate(GenerationPlan $plan): string
    {
        $this->lastSequence = null;
        $this->lastOutput = null;
        $requested = $plan->startRule();
        $root = $requested ?? $this->grammar->startSymbol;
        $this->coverage?->beginGeneration($root, ['budget' => $plan->expansionBudget(), 'lexicalTarget' => $plan->lexicalTarget()]);
        $this->coverage?->beginAttempt(0);
        try {
            $root = $requested !== null && $this->startSymbol !== null ? ($this->startSymbol)($requested) : $root;
            $sql = $plan->lexicalTarget() !== null ? $this->lexicalGrammar->generate($plan) : $this->realize($root, $plan);
            if ($sql === '' && $plan->requiresNonEmpty()) {
                throw GenerationException::planRequiresNonEmptyOutput($this->lexicalGrammar->version());
            }
            $this->coverage?->commitAttempt(
                hash('sha256', $sql),
                $this->lastSequence === null ? [] : (new SequenceObservation())->preserved($this->lastSequence)
            );
            return $sql;
        } finally {
            $this->coverage?->endGeneration();
        }
    }

    /**
     * Derives and realizes once, retaining source selection independently of transformed output.
     * @param GenerationPlan<bool> $plan
     * @throws GenerationException When derivation cannot complete
     * @throws LexicalException When a terminal has no compatible realization
     */
    public function realize(string $root, GenerationPlan $plan): string
    {
        $tokens = ($this->tokens ??= new TokenGenerator($this->grammar, $this->faker, $this->lexicalGrammar->isNonOutput(...)))->generate($root, $plan);
        $this->lastSequence = $this->rewriter?->rewrite($tokens) ?? $tokens;
        $this->coverage?->recordSequence($this->lastSequence);
        $this->lastOutput = $this->lexicalGrammar->resolveSequence($this->lastSequence, $plan, fn (int $count): int => $this->faker->numberBetween(0, $count - 1));
        $this->coverage?->recordOutput($this->lastOutput);
        return (new SqlSerializer())->serialize($this->lastOutput->pieces());
    }
}
