<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Generation;

/**
 * Combines fixed values without silently breaking an existing relation.
 * @visibility root
 */
final class RowBindings
{
    /**
     * @template TInherited
     * @template TOverride
     * @param array<TInherited> $inherited
     * @param array<TOverride> $overrides
     * @return array<TInherited|TOverride>
     * @throws RelationValueException
     */
    public function merge(string $table, array $inherited, array $overrides): array
    {
        foreach ($inherited as $column => $value) {
            if (array_key_exists($column, $overrides) && $value !== $overrides[$column]) {
                throw new RelationValueException($table, $column);
            }
        }
        return array_merge($inherited, $overrides);
    }
}
