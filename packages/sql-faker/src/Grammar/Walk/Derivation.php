<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Walk;

use Faker\Generator as FakerGenerator;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Symbol;
use SqlFaker\Grammar\Model\Terminal;

/**
 * Rewrites a start symbol into terminals by repeatedly taking a production.
 *
 * A derivation is a walk with a memory: how many rewrites it has spent so far
 * decides whether it may still choose freely or must start heading for an end,
 * and how many times it has already reached a rule decides which of the plan's
 * patterns applies. That count belongs to one walk, so a derivation is used
 * once and asked for its result.
 */
final class Derivation
{
    private const STEP_LIMIT = 5000;

    private int $steps = 0;

    /** @readonly */
    private ViableAlternatives $alternatives;

    /**
     * @param Grammar $grammar Grammar being walked
     * @param FakerGenerator $faker Source of the choices the walk makes freely
     * @param TerminationAnalyzer $analyzer Answers what a production still costs to finish
     */
    public function __construct(
        private readonly Grammar $grammar,
        private readonly FakerGenerator $faker,
        private readonly TerminationAnalyzer $analyzer,
        ?ViableAlternatives $alternatives = null,
    ) {
        $this->alternatives = $alternatives ?? new ViableAlternatives($analyzer);
    }

    /**
     * Walks from one symbol until nothing but terminals is left.
     *
     * @param string $startSymbol Symbol the walk begins at
     * @param GenerationPlan<bool> $plan Plan directing the walk
     *
     * @return list<Terminal> Terminals the walk arrived at
     *
     * @throws GenerationException When the grammar, the plan, or the step limit leaves no production to take
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
            $rule = $this->grammar->ruleMap[$nonTerminal->value]
                ?? throw GenerationException::unknownRule($nonTerminal->value);
            $occurrence = $occurrences[$nonTerminal->value] ?? 0;
            $occurrences[$nonTerminal->value] = $occurrence + 1;
            $alternatives = $this->alternatives->of(
                $rule,
                $plan->patternAt($nonTerminal->value, $occurrence),
                $this->steps === 1 && $plan->requiresNonEmpty(),
            );

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
     * Always the leftmost non-terminal, so one seed and one plan describe the
     * same walk every time it is run.
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
     * Answers which of the alternatives the walk takes.
     *
     * Up to the plan's depth the choice is free. Past it the walk is trying to
     * finish, so it takes whichever alternative writes the least.
     *
     * @param non-empty-array<int, Production> $alternatives Alternatives the grammar and the plan both allow
     * @param GenerationPlan<bool> $plan Plan directing the walk
     *
     * @return Production Alternative to rewrite with
     */
    public function selectProduction(array $alternatives, GenerationPlan $plan): Production
    {
        if ($this->steps < $plan->maxDepth()) {
            $keys = array_keys($alternatives);

            return $alternatives[$keys[$this->faker->numberBetween(0, count($keys) - 1)]];
        }

        $selected = $alternatives[array_key_first($alternatives)];
        $bestLength = $this->analyzer->estimateProductionLength($selected);
        foreach (array_slice($alternatives, 1) as $alternative) {
            $length = $this->analyzer->estimateProductionLength($alternative);
            if ($length < $bestLength) {
                $selected = $alternative;
                $bestLength = $length;
            }
        }

        return $selected;
    }
}
