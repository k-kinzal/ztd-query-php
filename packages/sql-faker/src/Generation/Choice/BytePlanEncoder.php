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
     * Lexical choices end at the first null answer; the compiler makes the remaining ones from the padding bytes.
     * The constraints must be ones the compiler accepts.
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
        $maximum = $constraints->expansionBudget() ?? BytePlanCompiler::MAXIMUM_BUDGET;
        if ($budget < $minimum || $budget > $maximum) {
            throw new InvalidArgumentException("Require a budget between the minimum of $minimum and the maximum of $maximum expansions.");
        }
        $structure = '';
        $lexical = '';
        $ended = false;
        $builder->build(
            $constraints,
            $budget,
            static function (int $count, array $candidates) use ($productionChoice, &$structure): int {
                $index = $productionChoice($count, $candidates);
                $structure .= ByteChoices::encode($count, $index);
                return $index;
            },
            static function (int $count) use ($lexicalChoice, &$lexical, &$ended): ?int {
                $index = $ended || $lexicalChoice === null ? null : $lexicalChoice($count);
                if ($index === null) {
                    $ended = true;
                    return null;
                }
                $lexical .= ByteChoices::encode($count, $index);
                return $index;
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
