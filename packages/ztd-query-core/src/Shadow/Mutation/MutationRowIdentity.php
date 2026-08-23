<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

final class MutationRowIdentity
{
    private const PREFIX = '__ztd_original_';

    public function column(string $primaryKey): string
    {
        return self::PREFIX . $primaryKey;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function strip(array $row): array
    {
        foreach (array_keys($row) as $column) {
            if (str_starts_with($column, self::PREFIX)) {
                unset($row[$column]);
            }
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $primaryKeys
     * @return array{row: array<string, mixed>, identity: array<string, mixed>}
     */
    public function extract(array $row, array $primaryKeys): array
    {
        $identity = [];
        foreach ($primaryKeys as $primaryKey) {
            $metadataColumn = $this->column($primaryKey);
            if (array_key_exists($metadataColumn, $row)) {
                $identity[$primaryKey] = $row[$metadataColumn];
                unset($row[$metadataColumn]);
                continue;
            }
            if (array_key_exists($primaryKey, $row)) {
                $identity[$primaryKey] = $row[$primaryKey];
            }
        }

        return ['row' => $row, 'identity' => $identity];
    }
}
