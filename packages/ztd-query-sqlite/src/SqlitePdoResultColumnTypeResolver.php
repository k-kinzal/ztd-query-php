<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;

final class SqlitePdoResultColumnTypeResolver implements ResultColumnTypeResolver
{
    public function resolve(array $metadata): ColumnType
    {
        $declaredType = $metadata['sqlite:decl_type'] ?? null;
        if (is_string($declaredType)) {
            return (new SqliteColumnTypeMapper())->map($declaredType);
        }

        $nativeType = $metadata['native_type'] ?? '';

        return (new SqliteColumnTypeMapper())->map(is_string($nativeType) ? $nativeType : '');
    }
}
