<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Choice;

use Faker\Generator;
use SqlFixture\Fixture\GenerationRun;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\Relation;

/**
 * Resolves choices before generating a row's parents or random column values.
 * @visibility root
 */
final class RowChoices
{
    /**
     * @template TValue
     * @param array<TValue> $values
     * @return ResolvedRow<TValue|string|int|bool|null>
     */
    public function resolve(FixturePlan $plan, string $table, array $values, ?Relation $arrival, GenerationRun $run, Generator $faker): ResolvedRow
    {
        foreach ($plan->choices as $choice) {
            if ($choice->discriminator->table !== $table) {
                continue;
            }
            $case = (new CaseSelection())->select($choice, $values, $arrival, $faker);
            $column = $choice->discriminator->columns[0];
            if (!array_key_exists($column, $values)) {
                $values[$column] = $case->value;
            }
            if ($arrival !== null && in_array($arrival, $choice->relations(), true)) {
                $arrival = (new CaseSelection())->matchingArrival($case, $arrival);
            }
            $values = (new ChoiceBindings())->fix($choice, $case, $values, $run);
            $plan = $plan->select($choice, $case);
        }
        return new ResolvedRow($plan, $values, $arrival);
    }
}
