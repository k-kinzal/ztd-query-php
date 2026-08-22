<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;

final class PgSqlPdoResultColumnTypeResolver implements ResultColumnTypeResolver
{
    public function resolve(array $metadata): ColumnType
    {
        $nativeType = $metadata['native_type'] ?? '';
        if (!is_string($nativeType)) {
            return (new PgSqlColumnTypeMapper())->map('');
        }

        $length = $metadata['len'] ?? null;
        if (is_int($length) && $length > 0 && !str_contains($nativeType, '(')) {
            $nativeType = match (strtoupper($nativeType)) {
                'VARCHAR' => "VARCHAR($length)",
                'BPCHAR' => "CHAR($length)",
                default => $nativeType,
            };
        }

        return (new PgSqlColumnTypeMapper())->map($nativeType);
    }
}
