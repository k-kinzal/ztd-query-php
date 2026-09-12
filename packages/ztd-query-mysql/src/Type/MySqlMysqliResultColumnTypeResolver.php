<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;

/**
 * Implements the My Sql Mysqli Result Column Type Resolver contract for MySQL.
 */
final class MySqlMysqliResultColumnTypeResolver implements ResultColumnTypeResolver
{
    /**
     * Resolve for the supplied MySQL input.
     */
    public function resolve(array $metadata): ColumnType
    {
        $type = $metadata['type'] ?? null;
        $charset = $metadata['charsetnr'] ?? null;
        $scalarTypes = [
            1 => 'TINYINT',
            2 => 'SMALLINT',
            3 => 'INTEGER',
            8 => 'BIGINT',
            9 => 'MEDIUMINT',
            13 => 'YEAR',
            16 => 'BIT',
            4 => 'FLOAT',
            5 => 'DOUBLE',
            6 => 'NULL',
            0 => 'DECIMAL', 246 => 'DECIMAL',
            10 => 'DATE', 14 => 'DATE',
            11 => 'TIME', 19 => 'TIME',
            12 => 'DATETIME', 18 => 'DATETIME',
            7 => 'TIMESTAMP', 17 => 'TIMESTAMP',
            242 => 'VECTOR',
            245 => 'JSON',
            247 => 'ENUM',
            248 => 'SET',
            15 => 'VARCHAR',
            255 => 'GEOMETRY',
        ];
        $stringTypes = [
            249 => ['TINYTEXT', 'TINYBLOB'],
            250 => ['MEDIUMTEXT', 'MEDIUMBLOB'],
            251 => ['LONGTEXT', 'LONGBLOB'],
            252 => ['TEXT', 'BLOB'],
            253 => ['VARCHAR', 'VARBINARY'],
            254 => ['CHAR', 'BINARY'],
        ];
        $binary = in_array($charset, [63, '63'], true) ? 1 : 0;
        $nativeType = is_int($type) ? ($scalarTypes[$type] ?? $stringTypes[$type][$binary] ?? '') : '';

        return (new MySqlColumnTypeMapper())->map($nativeType);
    }

}
