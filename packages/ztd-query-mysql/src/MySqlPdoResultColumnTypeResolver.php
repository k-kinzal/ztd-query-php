<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;

/**
 * The my sql pdo result column type resolver, as result column type resolver.
 */
final class MySqlPdoResultColumnTypeResolver implements ResultColumnTypeResolver
{
    /**
     * Answers.
     *
     * @return ColumnType
     */
    public function resolve(array $metadata): ColumnType
    {
        $nativeType = $metadata['native_type'] ?? '';

        return (new MySqlColumnTypeMapper())->map(is_string($nativeType) ? $nativeType : '');
    }
}
