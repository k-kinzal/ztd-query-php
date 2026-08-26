<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * The sqlite pdo result column type resolver, as result column type resolver.
 */
final class SqlitePdoResultColumnTypeResolver implements ResultColumnTypeResolver
{
    /**
     * Answers.
     *
     * @return ColumnDeclaration
     */
    public function resolve(array $metadata): ColumnDeclaration
    {
        $declaredType = $metadata['sqlite:decl_type'] ?? null;
        if (is_string($declaredType)) {
            return (new SqliteColumnTypeMapper())->map($declaredType);
        }

        $nativeType = $metadata['native_type'] ?? '';
        if (!is_string($nativeType) || trim($nativeType) === '' || strcasecmp($nativeType, 'null') === 0) {
            return new ColumnDeclaration(ColumnTypeFamily::UNKNOWN, is_string($nativeType) ? $nativeType : '');
        }
        if (strcasecmp($nativeType, 'string') === 0) {
            return new ColumnDeclaration(ColumnTypeFamily::STRING, $nativeType);
        }

        return (new SqliteColumnTypeMapper())->map($nativeType);
    }
}
