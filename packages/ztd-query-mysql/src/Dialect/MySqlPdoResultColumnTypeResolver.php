<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Dialect;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnDeclaration;

/**
 * The my sql pdo result column type resolver, as result column type resolver.
 */
final class MySqlPdoResultColumnTypeResolver implements ResultColumnTypeResolver
{
    /**
     * Answers.
     *
     * @return ColumnDeclaration
     */
    public function resolve(array $metadata): ColumnDeclaration
    {
        $nativeType = $metadata['native_type'] ?? '';

        return (new MySqlColumnTypeMapper())->map(is_string($nativeType) ? $nativeType : '');
    }
}
