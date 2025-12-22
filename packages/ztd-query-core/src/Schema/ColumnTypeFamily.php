<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * Platform-independent column type family.
 *
 * Normalizes column types across different database platforms into
 * a common set of type families for CAST rendering and type mapping.
 */
enum ColumnTypeFamily: string
{
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case DOUBLE = 'double';
    case DECIMAL = 'decimal';
    case STRING = 'string';
    case TEXT = 'text';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case TIME = 'time';
    case DATETIME = 'datetime';
    case TIMESTAMP = 'timestamp';
    case BINARY = 'binary';
    case JSON = 'json';
    case UNKNOWN = 'unknown';
}
