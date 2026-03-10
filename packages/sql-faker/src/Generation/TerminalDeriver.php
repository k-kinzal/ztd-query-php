<?php

declare(strict_types=1);

namespace SqlFaker\Generation;

use Faker\Generator as FakerGenerator;
use LogicException;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Contract\TerminationLengths;

final class TerminalDeriver
{
    private const DERIVATION_LIMIT = 5000;

    public function __construct(
        private readonly FakerGenerator $faker,
        private readonly string $defaultStartRule,
        private readonly bool $unknownNonTerminalAsLiteral = false,
    ) {
    }

    public function derive(Grammar $grammar, TerminationLengths $terminationLengths, GenerationRequest $request): TerminalSequence
    {
        if ($request->seed !== null) {
            $this->faker->seed($request->seed);
        }

        $derivationSteps = 0;
        $targetDepth = max(1, $request->maxDepth);
        $startRule = $request->startRule ?? $this->defaultStartRule;

        $form = [['name' => $startRule, 'isNonTerminal' => true]];

        while (true) {
            $index = null;
            foreach ($form as $candidateIndex => $symbol) {
                if ($symbol['isNonTerminal']) {
                    $index = $candidateIndex;
                    break;
                }
            }

            if ($index === null) {
                break;
            }

            $derivationSteps++;
            if ($derivationSteps > self::DERIVATION_LIMIT) {
                throw new LogicException('Exceeded derivation limit while generating SQL.');
            }

            $current = $form[$index];
            $rule = $grammar->rule($current['name']);
            if ($rule === null) {
                if ($this->unknownNonTerminalAsLiteral) {
                    $form[$index] = ['name' => $current['name'], 'isNonTerminal' => false];
                    continue;
                }

                throw new LogicException(sprintf('Unknown grammar rule: %s', $current['name']));
            }

            $alternatives = $rule->alternatives;
            if ($alternatives === []) {
                throw new LogicException('Production rule has no alternatives.');
            }

            if ($derivationSteps >= $targetDepth) {
                $selectedIndex = 0;
                $bestLength = PHP_INT_MAX;
                foreach ($alternatives as $alternativeIndex => $alternative) {
                    $estimatedLength = 0;
                    foreach ($alternative->symbols as $symbol) {
                        $estimatedLength += $symbol->isNonTerminal
                            ? $terminationLengths->lengthOf($symbol->name)
                            : 1;
                    }

                    if ($estimatedLength < $bestLength) {
                        $bestLength = $estimatedLength;
                        $selectedIndex = $alternativeIndex;
                    }
                }
            } else {
                $selectedIndex = $this->faker->numberBetween(0, count($alternatives) - 1);
            }

            $replacement = [];
            foreach ($alternatives[$selectedIndex]->symbols as $symbol) {
                $replacement[] = [
                    'name' => $symbol->name,
                    'isNonTerminal' => $symbol->isNonTerminal,
                ];
            }

            $form = [
                ...array_slice($form, 0, $index),
                ...$replacement,
                ...array_slice($form, $index + 1),
            ];
        }

        $terminals = [];
        foreach ($form as $symbol) {
            $terminals[] = $symbol['name'];
        }

        return new TerminalSequence($terminals);
    }
}
