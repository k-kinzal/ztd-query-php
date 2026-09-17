<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Choice;

use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationChoice;
use SqlFixture\Plan\Validation\PlanValidation;

/**
 * Allows overlapping bindings only between mutually exclusive cases.
 * @visibility root
 */
final class ChoiceValidation
{
    /**
     * @throws ChoiceDefinitionException
     */
    public function validate(PlanContents $contents): void
    {
        $seen = [];
        $earlier = $contents->unconditional;
        foreach ($contents->choices as $choice) {
            $key = $choice->discriminator->toString();
            if (in_array($key, $seen, true)) {
                throw new ChoiceDefinitionException($choice->discriminator, 'Combine cases for the same discriminator in one choice.');
            }
            $seen[] = $key;
            $this->validateChoice($choice, $earlier);
            $earlier = [...$earlier, ...$choice->relations()];
        }
    }

    /**
     * @param list<Relation> $earlier
     * @throws ChoiceDefinitionException
     */
    public function validateChoice(RelationChoice $choice, array $earlier): void
    {
        if ($choice->cases === []) {
            throw new ChoiceDefinitionException($choice->discriminator, 'Define at least one explicit case.');
        }
        $values = [];
        foreach ($choice->cases as $case) {
            if (in_array($case->value, $values, true)) {
                throw new ChoiceDefinitionException($choice->discriminator, 'Each discriminator value must be unique.');
            }
            $values[] = $case->value;
        }
        foreach ($choice->branches() as $branch) {
            (new PlanValidation())->rejectColumnsBoundTwice($branch->relations);
            foreach ($branch->relations as $relation) {
                $this->validateRelation($choice, $relation, $earlier);
            }
        }
    }

    /**
     * @param list<Relation> $earlier
     * @throws ChoiceDefinitionException
     */
    public function validateRelation(RelationChoice $choice, Relation $relation, array $earlier): void
    {
        if (!in_array($choice->discriminator->table, $relation->tables(), true)) {
            throw new ChoiceDefinitionException($choice->discriminator, 'Each conditional relation must touch the discriminator table. Declare further dependencies outside the choice.');
        }
        foreach ($earlier as $other) {
            if ($relation->child()->table === $other->child()->table && array_intersect($relation->child()->columns, $other->child()->columns) !== []) {
                throw new ChoiceDefinitionException($choice->discriminator, 'A conditional binding overlaps another simultaneously active relation.');
            }
        }
    }
}
