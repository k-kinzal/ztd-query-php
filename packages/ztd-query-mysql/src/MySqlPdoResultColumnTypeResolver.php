<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnDeclaration;

final class MySqlPdoResultColumnTypeResolver implements ResultColumnTypeResolver
{
    public function resolve(array $metadata): ColumnDeclaration
    {
        $nativeType = $metadata['native_type'] ?? '';

        return (new MySqlColumnTypeMapper())->map(is_string($nativeType) ? $nativeType : '');
    }
}
