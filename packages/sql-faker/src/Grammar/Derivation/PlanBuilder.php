<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Derivation;

use Closure;
use Faker\Generator;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Token\TokenGenerator;
use SqlFaker\Grammar\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalGrammar;
use SqlFaker\Grammar\NonTerminal;

/**
 * Compiles choices through the production generation pipeline into immutable instructions.
 */
final class PlanBuilder
{
    private readonly CompletionCosts $costs;
    private readonly TokenGenerator $tokens;

    /**
     * @param (Closure(string|null): string)|null $startSymbol Resolves explicit release aliases
     */
    public function __construct(
        private readonly Grammar $grammar,
        private readonly LexicalGrammar $lexical,
        private readonly ?TokenRewriter $rewriter = null,
        private readonly ?Closure $startSymbol = null,
    ) {
        $this->costs = new CompletionCosts($grammar, $lexical->isNonOutput(...));
        $this->tokens = new TokenGenerator($grammar, new Generator(), $lexical->isNonOutput(...));
    }

    /**
     * Includes descendant and repeated-occurrence constraints before drawing an expansion budget.
     * @param GenerationPlan<bool> $plan
     */
    public function minimumExpansions(GenerationPlan $plan): int
    {
        return (new ConstrainedCompletion($this->grammar, $this->costs))->minimum(
            [new NonTerminal($this->root($plan))],
            $plan,
            [],
            $plan->requiresNonEmpty(),
            $plan->expansionBudget() ?? 5000,
        );
    }

    /**
     * Uses the grammar entry point unless the caller explicitly requests a rule.
     * @param GenerationPlan<bool> $plan
     */
    public function root(GenerationPlan $plan): string
    {
        $requested = $plan->startRule();
        return $requested === null ? $this->grammar->startSymbol
            : ($this->startSymbol !== null ? ($this->startSymbol)($requested) : $requested);
    }

    /**
     * Freezes original production ordinals and every rewritten terminal's complete candidate.
     * @template T of bool
     * @param GenerationPlan<T> $constraints
     * @param Closure(int): ?int $productionChoice
     * @param Closure(int): ?int $lexicalChoice
     * @return GenerationPlan<T>
     */
    public function build(GenerationPlan $constraints, int $budget, Closure $productionChoice, Closure $lexicalChoice): GenerationPlan
    {
        $sequence = $this->tokens->generate($this->root($constraints), $constraints->withExpansionBudget($budget)->withStepBudget(), $productionChoice);
        $patterns = [];
        foreach ($sequence->productions as $production) {
            $patterns[$production->rule][] = ProductionPattern::at($production->ordinal);
        }
        $sequence = $this->rewriter?->rewrite($sequence) ?? $sequence;
        $output = $this->lexical->resolveSequence($sequence, $constraints, static fn (int $count): int => $lexicalChoice($count) ?? 0, $lexicalChoice);
        $lexemes = [];
        $keys = [];
        foreach ($sequence->terminals as $index => $terminal) {
            $candidate = $output->candidates[$index];
            $lexemes[$terminal->name][] = implode(' ', array_map(static fn (Lexeme $lexeme): string => $lexeme->text, $candidate->lexemes));
            $keys[$terminal->name][] = $candidate->key();
        }
        return new GenerationPlan(
            $constraints->startRule(),
            $patterns,
            [],
            $lexemes,
            null,
            [],
            $constraints->requiresNonEmpty(),
            $constraints->maxDepth(),
            true,
            $budget,
            $keys
        );
    }
}
