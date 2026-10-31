<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Choice;

use InvalidArgumentException;
use SqlFaker\Generation\Plan\GenerationPlan;

/**
 * Decodes bytes at the plan entry point and returns frozen production and lexical instructions.
 * Inputs of at most four bytes also supply production choices, so initial fuzz
 * mutations can select statements before the separate choice body grows.
 */
final class BytePlanCompiler
{
    /**
     * Largest expansion budget decoded for constraints that carry none of their own.
     */
    public const MAXIMUM_BUDGET = 5000;

    /**
     * No input bytes cross this boundary into the grammar walk or lexical value domains.
     * @param GenerationPlan<bool>|null $constraints
     * @return GenerationPlan<bool>
     * @throws InvalidArgumentException When the constraints cannot be completed within the configured budget
     */
    public function compile(string $input, PlanBuilder $builder, ?GenerationPlan $constraints = null): GenerationPlan
    {
        $constraints ??= GenerationPlan::all();
        $minimum = $builder->minimumExpansions($constraints);
        $maximum = $constraints->expansionBudget() ?? self::MAXIMUM_BUDGET;
        if ($minimum < 1 || $maximum < $minimum || $maximum > 1000000 || $constraints->lexicalTarget() !== null) {
            throw new InvalidArgumentException('Require a derivation plan with 1 <= minimum expansions <= maximum expansions <= 1000000.');
        }
        $header = 0;
        $range = $maximum - $minimum + 1;
        for ($index = 3; $index >= 0; --$index) {
            $header = ($header * 256 + (isset($input[$index]) ? ord($input[$index]) : 0)) % $range;
        }
        $structure = strlen($input) <= 4 ? $input : '';
        $lexical = '';
        foreach (str_split(substr($input, 4), 2) as $pair) {
            $structure .= $pair[0] ?? '';
            $lexical .= $pair[1] ?? '';
        }
        return $builder->build(
            $constraints,
            $minimum + $header,
            (new ByteChoices($structure))->index(...),
            (new ByteChoices($lexical))->index(...)
        );
    }
}
