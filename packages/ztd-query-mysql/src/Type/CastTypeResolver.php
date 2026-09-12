<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Type;

/**
 * Cast Type Resolver.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class CastTypeResolver
{
    /**
     * Extract Decimal Cast for the supplied MySQL input.
     */
    public function extractDecimalCast(string $nativeType): string
    {
        $upper = strtoupper($nativeType);
        if (preg_match('/DECIMAL\((\d+),(\d+)\)/', $upper, $matches) === 1) {
            return "DECIMAL({$matches[1]},{$matches[2]})";
        }
        if (preg_match('/DECIMAL\((\d+)\)/', $upper, $matches) === 1) {
            return "DECIMAL({$matches[1]},0)";
        }

        return 'DECIMAL(65,30)';
    }

    /**
     * Fallback mapping for UNKNOWN family using native type string.
     */
    public function mapNativeTypeToCastType(string $nativeType): string
    {
        $upperType = strtoupper($nativeType);
        $baseType = (string) preg_replace('/\(.*\)/', '', $upperType);

        return match ($baseType) {
            'INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT' => 'SIGNED',
            'DECIMAL', 'NUMERIC' => $this->extractDecimalCast($nativeType),
            'FLOAT' => 'FLOAT',
            'DOUBLE', 'REAL' => 'DOUBLE',
            'DATE' => 'DATE',
            'DATETIME', 'TIMESTAMP' => 'DATETIME',
            'TIME' => 'TIME',
            'YEAR' => 'YEAR',
            'JSON' => 'JSON',
            'BINARY', 'VARBINARY', 'BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB' => 'BINARY',
            default => 'CHAR',
        };
    }
}
