<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Choice;

use InvalidArgumentException;
use SqlFaker\Generation\Plan\GenerationPlan;

/**
 * Decodes bytes at the plan entry point and returns frozen production and lexical instructions.
 */
final class BytePlanCompiler
{
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
        $maximum = $constraints->expansionBudget() ?? 5000;
        if ($minimum < 1 || $maximum < $minimum || $maximum > 1000000 || $constraints->lexicalTarget() !== null) {
            throw new InvalidArgumentException('Require a derivation plan with 1 <= minimum expansions <= maximum expansions <= 1000000.');
        }
        $header = 0;
        $range = $maximum - $minimum + 1;
        for ($index = 3; $index >= 0; --$index) {
            $header = ($header * 256 + (isset($input[$index]) ? ord($input[$index]) : 0)) % $range;
        }
        $structure = '';
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
