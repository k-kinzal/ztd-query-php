<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use Faker\Generator as FakerGenerator;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Symbol;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminationAnalyzer;

/**
 * Rewrites a start symbol into terminals the way SQLite's grammar demands.
 *
 * SQLite's grammar is written for Lemon, and it differs from the Bison ones in
 * two ways a derivation has to answer for. A symbol Lemon never gives a rule to
 * is a token, not a missing rule, so reaching one means the walk has arrived
 * rather than gone wrong. And the grammar is recursive enough that a walk can
 * spend its whole step budget on the symbol in front of it and leave nothing
 * for the ones behind it, so each alternative is weighed against what finishing
 * the rest of the form will still cost.
 */
final class Derivation
{
    private const STEP_LIMIT = 5000;

    private int $steps = 0;

    /**
     * @param Grammar $grammar Grammar being walked
     * @param FakerGenerator $faker Source of the choices the walk makes freely
     * @param TerminationAnalyzer $analyzer Answers what a production still costs to finish
     */
    public function __construct(
        private readonly Grammar $grammar,
        private readonly FakerGenerator $faker,
        private readonly TerminationAnalyzer $analyzer,
    ) {
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
            if ($this->steps > self::STEP_LIMIT) {
                throw GenerationException::derivationLimitExceeded();
            }

            /** @var NonTerminal $nonTerminal */
            $nonTerminal = $form[$index];
            if (!isset($this->grammar->ruleMap[$nonTerminal->value])) {
                $form = [
                    ...array_slice($form, 0, $index),
                    new Terminal($nonTerminal->value),
                    ...array_slice($form, $index + 1),
                ];
                continue;
            }

            $rule = $this->grammar->ruleMap[$nonTerminal->value];
            if ($rule->alternatives === []) {
                throw GenerationException::ruleHasNoAlternatives($nonTerminal->value);
            }
            $alternatives = array_values(array_filter(
                $rule->alternatives,
                $this->analyzer->isProductionViable(...),
            ));
            if ($alternatives === []) {
                throw GenerationException::noRealizableAlternative($nonTerminal->value);
            }
            $occurrence = $occurrences[$nonTerminal->value] ?? 0;
            $occurrences[$nonTerminal->value] = $occurrence + 1;
            $pattern = $plan->patternAt($nonTerminal->value, $occurrence);
            if ($pattern !== null) {
                $alternatives = array_values(array_filter(
                    $alternatives,
                    static fn (Production $production): bool => $pattern->matches(array_map(
                        static fn (Symbol $symbol): string => $symbol->value(),
                        $production->symbols,
                    )),
                ));
                if ($alternatives === []) {
                    throw GenerationException::noAlternativeMatchingPlan($nonTerminal->value);
                }
            }
            if ($this->steps === 1 && $plan->requiresNonEmpty()) {
                $alternatives = array_values(array_filter(
                    $alternatives,
                    fn (Production $production): bool => $this->analyzer->estimateProductionLength($production) > 0,
                ));
                if ($alternatives === []) {
                    throw GenerationException::startRuleCannotProduceOutput($nonTerminal->value);
                }
            }

            $alternatives = $this->affordable($alternatives, new Production(array_slice($form, $index + 1)));
            $production = $this->selectProduction($alternatives, $plan);

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
            return $alternatives[$this->faker->numberBetween(0, count($alternatives) - 1)];
        }

        $selected = 0;
        $bestSteps = PHP_INT_MAX;
        $bestLength = PHP_INT_MAX;
        foreach ($alternatives as $index => $alternative) {
            $steps = $this->analyzer->estimateProductionSteps($alternative);
            $length = $this->analyzer->estimateProductionLength($alternative);
            if ($steps < $bestSteps || ($steps === $bestSteps && $length < $bestLength)) {
                $bestSteps = $steps;
                $bestLength = $length;
                $selected = $index;
            }
        }

        return $alternatives[$selected];
    }
}
