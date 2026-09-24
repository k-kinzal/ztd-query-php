<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Optimization;

/**
 * A MySQL query block option written after SELECT, other than DISTINCT and ALL, backed by its keyword.
 *
 * Options change how the server schedules, plans, buffers or caches the block, or whether it counts the rows
 * a LIMIT leaves out; none of them changes the rows the block returns.
 *
 * @visibility public
 * @example Reading the options of a query block
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a INT)'));
 *     $statement = $binder->bind('SELECT HIGH_PRIORITY STRAIGHT_JOIN a FROM t');
 *     $statement->options // => [\SqlSemantics\Model\Query\Optimization\SelectOption::HighPriority, \SqlSemantics\Model\Query\Optimization\SelectOption::StraightJoin]
 *     \SqlSemantics\Model\Query\Optimization\SelectOption::HighPriority->firstBlockOnly('mysql-8.4.7') // => true
 */
enum SelectOption: string
{
    case HighPriority = 'HIGH_PRIORITY';
    case StraightJoin = 'STRAIGHT_JOIN';
    case SmallResult = 'SQL_SMALL_RESULT';
    case BigResult = 'SQL_BIG_RESULT';
    case BufferResult = 'SQL_BUFFER_RESULT';
    case Cache = 'SQL_CACHE';
    case NoCache = 'SQL_NO_CACHE';
    case CalcFoundRows = 'SQL_CALC_FOUND_ROWS';

    /**
     * Whether the server takes the option only in the first query block of the outermost query: HIGH_PRIORITY,
     * SQL_CALC_FOUND_ROWS and SQL_BUFFER_RESULT always, and the query cache options before MySQL 8.0.
     *
     * @param string|null $grammarVersion MySQL grammar release tag; null means the newest release
     */
    public function firstBlockOnly(?string $grammarVersion): bool
    {
        return match ($this) {
            self::HighPriority, self::CalcFoundRows, self::BufferResult, self::Cache => true,
            self::NoCache => str_starts_with($grammarVersion ?? '', 'mysql-5.'),
            self::StraightJoin, self::SmallResult, self::BigResult => false,
        };
    }
}
