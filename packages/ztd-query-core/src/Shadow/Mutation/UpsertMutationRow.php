<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

final class UpsertMutationRow
{
    private const VALUE_PREFIX = '__ztd_upsert_value_';

    private const PREDICATE = '__ztd_upsert_predicate';

    public function valueColumn(int $index): string
    {
        return self::VALUE_PREFIX . $index;
    }

    public function predicateColumn(): string
    {
        return self::PREDICATE;
    }

    public function predicateMatches(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function incomingRow(array $row, int $valueCount): array
    {
        for ($index = 0; $index < $valueCount; ++$index) {
            unset($row[$this->valueColumn($index)]);
        }
        unset($row[self::PREDICATE]);

        return $row;
    }
}
