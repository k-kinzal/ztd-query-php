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
use SqlFaker\Grammar\Symbol;

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
     * Includes the first root pattern in the grammar's expansion lower bound.
     * Descendant costs remain grammar costs; occurrence-specific constraints can require more steps.
     * @param GenerationPlan<bool> $plan
     */
    public function minimumExpansions(GenerationPlan $plan): int
    {
        $root = $this->root($plan);
        $pattern = $plan->patternAt($root, 0);
        if ($pattern === null) {
            return $this->costs->rule($root, $plan->requiresNonEmpty());
        }
        $minimum = PHP_INT_MAX;
        foreach (($this->grammar->ruleMap[$root]->alternatives ?? []) as $ordinal => $production) {
            if ($pattern->matches(array_map(static fn (Symbol $symbol): string => $symbol->value(), $production->symbols), $ordinal)) {
                $minimum = min($minimum, CompletionCosts::add(1, $this->costs->completion($production, [], $plan->requiresNonEmpty())));
            }
        }
        return $minimum;
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
        $output = $this->lexical->resolveSequence($sequence, $constraints, static fn (int $count): int => $lexicalChoice($count) ?? 0);
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
