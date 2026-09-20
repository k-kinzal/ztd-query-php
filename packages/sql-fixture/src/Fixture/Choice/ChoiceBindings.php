<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Choice;

use SqlFixture\Fixture\GenerationRun;
use SqlFixture\Plan\Choice\ChoiceCase;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationChoice;

/**
 * Clears inactive foreign keys and absent optional parents explicitly.
 * @visibility root
 */
final class ChoiceBindings
{
    /**
     * @template TValue
     * @param array<TValue> $values
     * @return array<TValue|null>
     * @throws ChoiceValueException
     */
    public function fix(RelationChoice $choice, ChoiceCase $case, array $values, GenerationRun $run): array
    {
        $all = $this->childColumns($choice, $choice->relations());
        $active = $this->childColumns($choice, $case->relations);
        foreach (array_diff($all, $active) as $column) {
            if (isset($values[$column])) {
                throw new ChoiceValueException($choice->discriminator, sprintf('Inactive reference %s must be NULL.', $column));
            }
            $values[$column] = null;
        }
        foreach ($case->relations as $relation) {
            if ($relation->child()->table !== $choice->discriminator->table || !$relation->parentIsOptional() || $run->wasAskedFor($relation->parent()->table)) {
                continue;
            }
            foreach ($relation->child()->columns as $column) {
                if (!array_key_exists($column, $values)) {
                    $values[$column] = null;
                }
            }
        }
        return $values;
    }

    /**
     * Lists the foreign key columns managed on the discriminator row.
     * @param list<Relation> $relations
     * @return list<string>
     */
    public function childColumns(RelationChoice $choice, array $relations): array
    {
        $columns = [];
        foreach ($relations as $relation) {
            if ($relation->child()->table === $choice->discriminator->table) {
                $columns = [...$columns, ...$relation->child()->columns];
            }
        }
        return array_values(array_unique($columns));
    }
}
