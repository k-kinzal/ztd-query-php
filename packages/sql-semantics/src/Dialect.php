<?php

declare(strict_types=1);

namespace SqlSemantics;

/**
 * The database language whose binding and type rules apply.
 *
 * @example Reading semantic facts
 *     \SqlSemantics\Dialect::PostgreSql->value // => 'postgresql'
 *
 * @visibility public
 */
enum Dialect: string
{
    case PostgreSql = 'postgresql';
    case MySql = 'mysql';
    case Sqlite = 'sqlite';
}
