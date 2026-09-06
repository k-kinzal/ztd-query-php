<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Input;

use LogicException;
use SqlFaker\Grammar\Choice\ByteChoices;
use SqlFaker\Grammar\Derivation\CompletionCosts;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;

/**
 * Encodes expansion choices after the same viability and sibling-budget filtering as generation.
 */
final class WitnessEncoder
{
    /**
     * Binds the fixed grammar and input budget range.
     */
    public function __construct(
        private readonly Grammar $grammar,
        private readonly CompletionCosts $costs,
        private readonly FuzzPlanDecoder $decoder
    ) {
    }

    /**
     * Encodes one complete minimum witness, including its siblings.
     *
     * @throws LogicException When a witness disagrees with the actual candidate policy
     */
    public function encode(WitnessNode $witness): string
    {
        $form = [new NonTerminal($witness->rule)];
        $structure = '';
        $steps = 0;
        foreach ($witness->sequence() as [$name, $ordinal]) {
            $index = 0;
            while (isset($form[$index]) && !$form[$index] instanceof NonTerminal) {
                ++$index;
            }
            if (($form[$index] ?? null)?->value() !== $name) {
                throw new LogicException('Witness expansion order differs from the sentential form.');
            }
            $remainder = array_slice($form, $index + 1);
            ++$steps;
            $candidates = $this->costs->affordable(
                $this->grammar->ruleMap[$name]->alternatives,
                $remainder,
                $index === 0,
                $witness->cost - $steps
            );
            $production = $this->grammar->ruleMap[$name]->alternatives[$ordinal];
            $choice = array_search($production, $candidates, true);
            if ($choice === false) {
                throw new LogicException('Witness production is not affordable after candidate filtering.');
            }
            $width = ByteChoices::width(count($candidates));
            for ($byte = 0; $byte < $width; ++$byte) {
                $structure .= chr($choice % 256);
                $choice = intdiv($choice, 256);
            }
            array_splice($form, $index, 1, $production->symbols);
        }
        return $this->decoder->encode($witness->cost, $structure);
    }
}
