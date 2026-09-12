<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Derivation;

use Closure;
use Faker\Generator as FakerGenerator;
use SqlFaker\Generation\Exception\GenerationException;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Symbol;
use SqlFaker\Grammar\Model\Terminal;

/**
 * Derives terminals from a common grammar while reserving enough steps to finish the remaining form.
 *
 * @visibility root
 */
final class Derivation
{
    private const STEP_LIMIT = 5000;

    private int $steps = 0;

    private readonly CompletionCosts $completion;
    private readonly ConstrainedCompletion $constrainedCompletion;

    /**
     * Occurrences from the most recent derivation, including empty productions.
     */
    public ?DerivationTrace $trace = null;

    /**
     * @param Grammar $grammar Grammar being walked
     * @param FakerGenerator $faker Source of the choices the walk makes freely
     * @param TerminationAnalyzer $analyzer Answers what a production still costs to finish
     * @param (Closure(int): ?int)|null $choose Optional choice policy; null results choose the shortest completion
     */
    public function __construct(
        private readonly Grammar $grammar,
        private readonly FakerGenerator $faker,
        private readonly TerminationAnalyzer $analyzer,
        ?CompletionCosts $completion = null,
        private readonly ?Closure $choose = null,
    ) {
        $this->completion = $completion ?? new CompletionCosts($grammar, static fn (string $terminal): bool => false);
        $this->constrainedCompletion = new ConstrainedCompletion($grammar, $this->completion);
    }

    /**
     * Walks from one symbol until nothing but terminals is left.
     *
     * @param string $startSymbol Symbol the walk begins at
     * @param GenerationPlan<bool> $plan Plan directing the walk
     *
     * @return list<Terminal> Terminals the walk arrived at
     *
     * @throws GenerationException When the grammar, the plan, or the step budget leaves no production to take
     */
    public function of(string $startSymbol, GenerationPlan $plan): array
    {
        $this->steps = 0;
        $this->trace = new DerivationTrace($startSymbol);
        /** @var list<Symbol> $form */
        $form = [new NonTerminal($startSymbol)];
        /** @var array<string, int> $occurrences */
        $occurrences = [];

        while (true) {
            $index = $this->firstNonTerminal($form);
            if ($index === null) {
                break;
            }

            $this->steps++;
            if ($this->steps > ($plan->expansionBudget() ?? self::STEP_LIMIT)) {
                throw GenerationException::derivationLimitExceeded();
            }

            /** @var NonTerminal $nonTerminal */
            $nonTerminal = $form[$index];
            $occurrence = $occurrences[$nonTerminal->value] ?? 0;
            $occurrences[$nonTerminal->value] = $occurrence + 1;
            $alternatives = $this->alternatives($nonTerminal, $plan, $occurrence);

            $alternatives = count($alternatives) === 1
                ? $this->affordableCompletion($alternatives, $form, $index, $plan)
                : $this->completable($alternatives, $form, $index, $plan, $occurrences);
            $production = $this->selectProduction($alternatives, $plan);
            $ordinal = array_search($production, $this->grammar->ruleMap[$nonTerminal->value]->alternatives, true);
            $this->trace->expand($index, $production, $ordinal === false ? 0 : $ordinal);

            $form = [
                ...array_slice($form, 0, $index),
                ...$production->symbols,
                ...array_slice($form, $index + 1),
            ];
        }

        /** @var list<Terminal> $form */
        return $form;
    }

    /**
     * Answers where in the sentential form the walk acts next.
     *
     * @param list<Symbol> $form Sentential form the walk has reached
     *
     * @return int|null Position of the leftmost non-terminal, or null when only terminals are left
     */
    public function firstNonTerminal(array $form): ?int
    {
        foreach ($form as $index => $symbol) {
            if ($symbol instanceof NonTerminal) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Keeps only the alternatives the walk can still afford to finish.
     *
     * What is left of the form behind the symbol being rewritten has to be
     * derived too, so the budget an alternative may spend is the step limit
     * less what has been spent and less what the remainder will cost.
     *
     * @param non-empty-list<Production> $alternatives Alternatives the grammar and the plan both allow
     * @param Production $remainder What the walk still has to derive behind this symbol
     *
     * @return non-empty-list<Production> Alternatives that still leave room to finish
     *
     * @throws GenerationException When none of them fits in the remaining budget
     */
    public function affordable(array $alternatives, Production $remainder): array
    {
        $budget = self::STEP_LIMIT - $this->steps - $this->analyzer->estimateProductionSteps($remainder);
        if ($budget < 0) {
            throw GenerationException::derivationLimitExceeded();
        }

        $affordable = array_values(array_filter(
            $alternatives,
            fn (Production $alternative): bool => $this->analyzer->estimateProductionSteps($alternative) <= $budget,
        ));
        if ($affordable === []) {
            throw GenerationException::derivationLimitExceeded();
        }

        return $affordable;
    }

    /**
     * Answers which of the alternatives the walk takes.
     *
     * Up to the plan's depth the choice is free. Past it the walk is trying to
     * finish, so it takes whichever alternative gets there in the fewest steps,
     * and among equals whichever writes the least.
     *
     * @param non-empty-list<Production> $alternatives Alternatives the walk may still take
     * @param GenerationPlan<bool> $plan Plan directing the walk
     *
     * @return Production Alternative to rewrite with
     */
    public function selectProduction(array $alternatives, GenerationPlan $plan): Production
    {
        if ($this->steps < $plan->maxDepth()) {
            $index = $this->choose === null
                ? $this->faker->numberBetween(0, count($alternatives) - 1)
                : ($this->choose)(count($alternatives));
            if ($index !== null) {
                return $alternatives[$index];
            }
        }

        $selected = 0;
        $bestSteps = PHP_INT_MAX;
        $bestLength = PHP_INT_MAX;
        foreach ($alternatives as $index => $alternative) {
            $steps = $plan->usesStepBudget() ? $this->analyzer->estimateProductionSteps($alternative) : 0;
            $length = $this->analyzer->estimateProductionLength($alternative);
            if ($steps < $bestSteps || ($steps === $bestSteps && $length < $bestLength)) {
                $bestSteps = $steps;
                $bestLength = $length;
                $selected = $index;
            }
        }

        return $alternatives[$selected];
    }

    /**
     * Finds realizable alternatives satisfying the plan at this occurrence.
     *
     * @param GenerationPlan<bool> $plan Plan directing the walk
     * @return non-empty-list<Production>
     *
     * @throws GenerationException When no alternative satisfies the grammar and plan
     */
    public function alternatives(NonTerminal $nonTerminal, GenerationPlan $plan, int $occurrence): array
    {
        $rule = $this->grammar->ruleMap[$nonTerminal->value]
            ?? throw GenerationException::unknownRule($nonTerminal->value);
        if ($rule->alternatives === []) {
            throw GenerationException::ruleHasNoAlternatives($nonTerminal->value);
        }
        $alternatives = array_filter($rule->alternatives, $this->analyzer->isProductionViable(...));
        if ($alternatives === []) {
            throw GenerationException::noRealizableAlternative($nonTerminal->value);
        }
        $pattern = $plan->patternAt($nonTerminal->value, $occurrence);
        if ($pattern !== null) {
            $alternatives = array_values(array_filter(
                $alternatives,
                static fn (Production $production, int $ordinal): bool => $pattern->matches(array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $production->symbols,
                ), $ordinal),
                ARRAY_FILTER_USE_BOTH,
            ));
            if ($alternatives === []) {
                throw GenerationException::noAlternativeMatchingPlan($nonTerminal->value);
            }
        }

        return array_values($alternatives);
    }

    /**
     * Reserves a complete non-empty continuation when required, independently of lexical handler availability.
     * @param non-empty-list<Production> $alternatives
     * @param list<Symbol> $form
     * @param GenerationPlan<bool> $plan
     * @return non-empty-list<Production>
     * @param array<string, int> $occurrences Occurrences already selected before the pending continuation
     * @throws GenerationException When the explicit plan has no affordable completion
     */
    public function completable(array $alternatives, array $form, int $index, GenerationPlan $plan, array $occurrences = []): array
    {
        $nonEmpty = $plan->requiresNonEmpty() && !$this->completion->hasTerminalOutput(array_slice($form, 0, $index));
        $candidates = $this->affordableCompletion($alternatives, $form, $index, $plan);
        if ($plan->hasRemainingPatterns($occurrences)) {
            $budget = ($plan->expansionBudget() ?? self::STEP_LIMIT) - $this->steps;
            $candidates = array_values(array_filter($candidates, fn (Production $production): bool =>
                $this->constrainedCompletion->within([...$production->symbols, ...array_slice($form, $index + 1)], $plan, $occurrences, $nonEmpty, $budget)));
        }
        if ($candidates === []) {
            throw GenerationException::derivationLimitExceeded();
        }
        return $candidates;
    }

    /**
     * Checks budget/output lower bounds when the next production is forced; the walk validates subsequent forced steps directly.
     * Looking ahead cannot select a different production here, so it would only repeat the frozen plan's remaining walk.
     * @param non-empty-list<Production> $alternatives
     * @param list<Symbol> $form
     * @param GenerationPlan<bool> $plan
     * @return non-empty-list<Production>
     * @throws GenerationException When no production can satisfy the remaining budget and output requirement
     */
    public function affordableCompletion(array $alternatives, array $form, int $index, GenerationPlan $plan): array
    {
        $nonEmpty = $plan->requiresNonEmpty() && !$this->completion->hasTerminalOutput(array_slice($form, 0, $index));
        $candidates = $this->completion->affordable($alternatives, array_slice($form, $index + 1), $nonEmpty, ($plan->expansionBudget() ?? self::STEP_LIMIT) - $this->steps);
        return $candidates === [] ? throw GenerationException::derivationLimitExceeded() : $candidates;
    }
}
