<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

final class MultiTableMutationRow
{
    private const PREFIX = '__ztd_multi_';

    public function valueColumn(int $targetIndex, int $columnIndex): string
    {
        return self::PREFIX . $targetIndex . '_value_' . $columnIndex;
    }

    public function identityColumn(int $targetIndex, int $primaryKeyIndex): string
    {
        return self::PREFIX . $targetIndex . '_identity_' . $primaryKeyIndex;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $columns
     * @return array<string, mixed>|null
     */
    public function values(array $row, int $targetIndex, array $columns): ?array
    {
        $values = [];
        foreach ($columns as $columnIndex => $column) {
            $metadataColumn = $this->valueColumn($targetIndex, $columnIndex);
            if (!array_key_exists($metadataColumn, $row)) {
                return null;
            }
            $values[$column] = $row[$metadataColumn];
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $primaryKeys
     * @return array<string, mixed>|null
     */
    public function identity(array $row, int $targetIndex, array $primaryKeys): ?array
    {
        $identity = [];
        foreach ($primaryKeys as $primaryKeyIndex => $primaryKey) {
            $metadataColumn = $this->identityColumn($targetIndex, $primaryKeyIndex);
            if (!array_key_exists($metadataColumn, $row)) {
                return null;
            }
            $identity[$primaryKey] = $row[$metadataColumn];
        }

        return $identity;
    }
}
