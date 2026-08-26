<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnDeclaration;

/**
 * The my sql mysqli result column type resolver, as result column type resolver.
 */
final class MySqlMysqliResultColumnTypeResolver implements ResultColumnTypeResolver
{
    /**
     * Answers.
     *
     * @return ColumnDeclaration
     */
    public function resolve(array $metadata): ColumnDeclaration
    {
        $type = $metadata['type'] ?? null;
        $charset = $metadata['charsetnr'] ?? null;
        $nativeType = is_int($type) ? $this->nativeType($type, $charset) : '';

        return (new MySqlColumnTypeMapper())->map($nativeType);
    }

    /**
     * Answers the type MySQL would have declared a column of this driver type.
     *
     * The driver numbers text and its binary counterpart the same, and tells
     * them apart only by the charset it reports, so both have to be read
     * together to know which of the two a column is.
     *
     * @param int $type Type as the driver numbers it
     * @param mixed $charset Charset as the driver numbers it
     *
     * @return string The type MySQL would have written, or empty where the driver names one ZTD does not know
     */
    public function nativeType(int $type, mixed $charset): string
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
            6 => 'NULL',
            0, 246 => 'DECIMAL',
            10, 14 => 'DATE',
            11, 19 => 'TIME',
            12, 18 => 'DATETIME',
            7, 17 => 'TIMESTAMP',
            242 => 'VECTOR',
            245 => 'JSON',
            247 => 'ENUM',
            248 => 'SET',
            249 => $this->isBinaryCharset($charset) ? 'TINYBLOB' : 'TINYTEXT',
            250 => $this->isBinaryCharset($charset) ? 'MEDIUMBLOB' : 'MEDIUMTEXT',
            251 => $this->isBinaryCharset($charset) ? 'LONGBLOB' : 'LONGTEXT',
            252 => $this->isBinaryCharset($charset) ? 'BLOB' : 'TEXT',
            253 => $this->isBinaryCharset($charset) ? 'VARBINARY' : 'VARCHAR',
            254 => $this->isBinaryCharset($charset) ? 'BINARY' : 'CHAR',
            15 => 'VARCHAR',
            255 => 'GEOMETRY',
            default => '',
        };
    }

    /**
     * Reports whether a charset is the one MySQL reports for bytes rather than text.
     *
     * @param mixed $charset Charset as the driver reports it
     *
     * @return bool True when the column holds bytes
     */
    public function isBinaryCharset(mixed $charset): bool
    {
        return $charset === 63 || $charset === '63';
    }
}
