<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Choice;

use Closure;
use InvalidArgumentException;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Grammar\Model\Production;

/**
 * Records the decisions of one guided plan construction as the bytes BytePlanCompiler decodes to the same plan.
 */
final class BytePlanEncoder
{
    /**
     * Runs the builder once with the caller's choices and writes the header, production and lexical bytes the compiler reads.
     * Lexical decisions the caller leaves open are made the way the compiler will make them from the padding bytes,
     * so compiling the result rebuilds this very plan.
     *
     * @param GenerationPlan<bool>|null $constraints
     * @param Closure(int, non-empty-list<Production>): int $productionChoice Selects one candidate at every expansion
     * @param (Closure(int): ?int)|null $lexicalChoice Selects lexical candidates until it answers null
     * @throws InvalidArgumentException When the budget lies outside the range the compiler decodes for these constraints
     */
    public function encode(PlanBuilder $builder, ?GenerationPlan $constraints, int $budget, Closure $productionChoice, ?Closure $lexicalChoice = null): string
    {
        $constraints ??= GenerationPlan::all();
        $minimum = $builder->minimumExpansions($constraints);
        $maximum = $constraints->expansionBudget() ?? 5000;
        if ($minimum < 1 || $budget < $minimum || $budget > $maximum || $maximum > 1000000 || $constraints->lexicalTarget() !== null) {
            throw new InvalidArgumentException('Require a derivation plan with 1 <= minimum expansions <= budget <= maximum expansions <= 1000000.');
        }
        $structure = '';
        $lexical = '';
        $padding = null;
        $builder->build(
            $constraints,
            $budget,
            static function (int $count, array $candidates) use ($productionChoice, &$structure): int {
                $index = $productionChoice($count, $candidates);
                $structure .= ByteChoices::encode($count, $index);
                return $index;
            },
            static function (int $count) use ($lexicalChoice, &$structure, &$lexical, &$padding): ?int {
                $index = $padding === null && $lexicalChoice !== null ? $lexicalChoice($count) : null;
                if ($index !== null) {
                    $lexical .= ByteChoices::encode($count, $index);
                    return $index;
                }
                $padding ??= new ByteChoices(str_repeat("\0", max(0, strlen($structure) - 1 - strlen($lexical))));
                return $padding->index($count);
            },
        );
        return pack('V', $budget - $minimum) . self::interleave($structure, $lexical);
    }

    /**
     * Pairs production bytes with lexical bytes the way the compiler splits them, padding the shorter stream with zero.
     */
    public static function interleave(string $structure, string $lexical): string
    {
        $pairs = max(strlen($structure), strlen($lexical));
        $structure = str_pad($structure, $pairs, "\0");
        $lexical = str_pad($lexical, max(strlen($lexical), $pairs - 1), "\0");
        $bytes = '';
        for ($index = 0; $index < $pairs; ++$index) {
            $bytes .= $structure[$index] . ($lexical[$index] ?? '');
        }
        return $bytes;
    }
}
