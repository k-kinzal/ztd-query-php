<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

/**
 * The built-in functions whose name is a keyword of the grammar and whose arguments are a plain list of expressions.
 *
 * Each case holds the keyword the function is written with. The grammar
 * names them in function_call_keyword, function_call_nonkeyword,
 * function_call_conflict, geometry_function and grouping_operation. SUBSTR
 * and MID are the keyword SUBSTRING, SCHEMA() is DATABASE(), SESSION_USER()
 * and SYSTEM_USER() are USER() to the lexer; ADDDATE and SUBDATE here are
 * the forms with a number of days.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/built-in-function-reference.html,
 * https://dev.mysql.com/doc/refman/8.4/en/function-resolution.html.
 *
 * @visibility public
 * @example Reading the keyword and the argument counts of a case
 *     [\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::Substring->value, \SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::Substring->arity()] // => ['SUBSTRING', [2, 3]]
 */
enum KeywordFunction: string
{
    case CurrentUser = 'CURRENT_USER';
    case Date = 'DATE';
    case Day = 'DAY';
    case Hour = 'HOUR';
    case Insert = 'INSERT';
    case Interval = 'INTERVAL';
    case Left = 'LEFT';
    case Minute = 'MINUTE';
    case Month = 'MONTH';
    case Right = 'RIGHT';
    case Second = 'SECOND';
    case Time = 'TIME';
    case Timestamp = 'TIMESTAMP';
    case Year = 'YEAR';
    case User = 'USER';
    case Ascii = 'ASCII';
    case Charset = 'CHARSET';
    case Coalesce = 'COALESCE';
    case Collation = 'COLLATION';
    case Database = 'DATABASE';
    case If = 'IF';
    case Format = 'FORMAT';
    case Microsecond = 'MICROSECOND';
    case Mod = 'MOD';
    case OldPassword = 'OLD_PASSWORD';
    case Password = 'PASSWORD';
    case Quarter = 'QUARTER';
    case Repeat = 'REPEAT';
    case Replace = 'REPLACE';
    case Reverse = 'REVERSE';
    case RowCount = 'ROW_COUNT';
    case Truncate = 'TRUNCATE';
    case Week = 'WEEK';
    case Contains = 'CONTAINS';
    case GeometryCollection = 'GEOMETRYCOLLECTION';
    case LineString = 'LINESTRING';
    case MultiLineString = 'MULTILINESTRING';
    case MultiPoint = 'MULTIPOINT';
    case MultiPolygon = 'MULTIPOLYGON';
    case Point = 'POINT';
    case Polygon = 'POLYGON';
    case AddDate = 'ADDDATE';
    case SubDate = 'SUBDATE';
    case Substring = 'SUBSTRING';
    case Log = 'LOG';
    case Grouping = 'GROUPING';

    /**
     * Answers the smallest and the largest number of arguments the grammar accepts; -1 is any number.
     *
     * @return array{int, int}
     */
    public function arity(): array
    {
        return match ($this) {
            self::CurrentUser, self::User, self::Database, self::RowCount => [0, 0],
            self::Date, self::Day, self::Hour, self::Minute, self::Month, self::Second, self::Time, self::Year, self::Ascii,
            self::Charset, self::Collation, self::Microsecond, self::OldPassword, self::Password, self::Quarter, self::Reverse => [1, 1],
            self::Left, self::Right, self::Mod, self::Repeat, self::Truncate, self::Contains, self::Point, self::AddDate, self::SubDate => [2, 2],
            self::Timestamp, self::Week, self::Log => [1, 2],
            self::Format, self::Substring => [2, 3],
            self::If, self::Replace => [3, 3],
            self::Insert => [4, 4],
            self::Interval => [2, -1],
            self::Coalesce, self::LineString, self::MultiLineString, self::MultiPoint, self::MultiPolygon, self::Polygon, self::Grouping => [1, -1],
            self::GeometryCollection => [0, -1],
        };
    }

    /**
     * Answers the result code (MYSQL-CALL-RESULT-001) of the function; ADDDATE and SUBDATE are typed as date arithmetic.
     */
    public function result(): string
    {
        return match ($this) {
            self::CurrentUser, self::User, self::Charset, self::Collation => 'TN',
            self::Date => 'AY',
            self::Day, self::Hour, self::Minute, self::Month, self::Second, self::Year, self::Microsecond, self::Quarter, self::Week, self::Contains => 'IY',
            self::Insert, self::Left, self::Right, self::Replace, self::Reverse, self::Substring => 'SP',
            self::Interval, self::RowCount, self::Grouping => 'IN',
            self::Time => 'MY',
            self::Timestamp => 'EY',
            self::Ascii => 'IP',
            self::Coalesce => '+C',
            self::Database, self::Format, self::Password => 'TY',
            self::If => 'ZR',
            self::Mod => 'OY',
            self::OldPassword => 'TP',
            self::Repeat => 'SY',
            self::Truncate => 'HP',
            self::GeometryCollection, self::LineString, self::MultiLineString, self::MultiPoint, self::MultiPolygon, self::Point, self::Polygon => 'GY',
            self::AddDate, self::SubDate => 'EY',
            self::Log => 'DY',
        };
    }

}
