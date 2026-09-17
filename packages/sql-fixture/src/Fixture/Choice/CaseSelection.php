<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Choice;

use Faker\Generator;
use SqlFixture\Plan\Choice\ChoiceCase;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationChoice;

/**
 * Chooses a branch from explicit values or compatible inverse relations.
 * @visibility root
 */
final class CaseSelection
{
    /**
     * @template TValue
     * @param array<TValue> $values
     * @throws ChoiceValueException
     */
    public function select(RelationChoice $choice, array $values, ?Relation $arrival, Generator $faker): ChoiceCase
    {
        $column = $choice->discriminator->columns[0];
        if (array_key_exists($column, $values)) {
            foreach ($choice->cases as $case) {
                if ($values[$column] === $case->value) {
                    return $this->checkArrival($choice, $case, $arrival);
                }
            }
            if ($choice->fallback !== null) {
                return $this->checkArrival($choice, $choice->fallback, $arrival);
            }
            throw new ChoiceValueException($choice->discriminator, 'The supplied value does not match any case. Define otherwise() to accept other values.');
        }
        $cases = $choice->cases;
        if ($arrival !== null && in_array($arrival, $choice->relations(), true)) {
            $cases = array_values(array_filter($cases, fn (ChoiceCase $case): bool => $this->matchingArrival($case, $arrival) !== null));
        }
        if ($cases === []) {
            throw new ChoiceValueException($choice->discriminator, 'An inverse fallback relation requires an explicit discriminator value.');
        }
        return $cases[$faker->numberBetween(0, count($cases) - 1)];
    }

    /**
     * Rejects an explicit case incompatible with the relation already walked.
     * @throws ChoiceValueException
     */
    public function checkArrival(RelationChoice $choice, ChoiceCase $case, ?Relation $arrival): ChoiceCase
    {
        if ($arrival !== null && in_array($arrival, $choice->relations(), true) && $this->matchingArrival($case, $arrival) === null) {
            throw new ChoiceValueException($choice->discriminator, 'The supplied value conflicts with the relation used to reach this row.');
        }
        return $case;
    }

    /**
     * Finds the selected equivalent of an inverse candidate relation.
     */
    public function matchingArrival(ChoiceCase $case, Relation $arrival): ?Relation
    {
        foreach ($case->relations as $relation) {
            if ($relation->parent()->equals($arrival->parent()) && $relation->child()->equals($arrival->child()) && $relation->kind === $arrival->kind) {
                return $relation;
            }
        }
        return null;
    }
}
