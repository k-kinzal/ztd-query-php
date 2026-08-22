<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;

final class MySqlMysqliResultColumnTypeResolver implements ResultColumnTypeResolver
{
    public function resolve(array $metadata): ColumnType
    {
        $type = $metadata['type'] ?? null;
        $charset = $metadata['charsetnr'] ?? null;
        $nativeType = is_int($type) ? $this->nativeType($type, $charset) : '';

        return (new MySqlColumnTypeMapper())->map($nativeType);
    }

    private function nativeType(int $type, mixed $charset): string
    {
        return match ($type) {
            1 => 'TINYINT',
            2 => 'SMALLINT',
            3 => 'INTEGER',
            8 => 'BIGINT',
            9 => 'MEDIUMINT',
            13 => 'YEAR',
            16 => 'BIT',
            4 => 'FLOAT',
            5 => 'DOUBLE',
            0, 246 => 'DECIMAL',
            10, 14 => 'DATE',
            11, 19 => 'TIME',
            12, 18 => 'DATETIME',
            7, 17 => 'TIMESTAMP',
            245 => 'JSON',
            249, 250, 251, 252 => $this->isBinaryCharset($charset) ? 'BLOB' : 'TEXT',
            15, 253, 254, 247, 248 => 'VARCHAR',
            default => '',
        };
    }

    private function isBinaryCharset(mixed $charset): bool
    {
        return $charset === 63 || $charset === '63';
    }
}
