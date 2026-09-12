<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;

/**
 * Implements the My Sql Pdo Result Column Type Resolver contract for MySQL.
 */
final class MySqlPdoResultColumnTypeResolver implements ResultColumnTypeResolver
{
    /**
     * Resolve for the supplied MySQL input.
     */
    public function resolve(array $metadata): ColumnType
    {
        $nativeType = $metadata['native_type'] ?? '';

        return (new MySqlColumnTypeMapper())->map(is_string($nativeType) ? $nativeType : '');
    }
}
