<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

/**
 * The storage class SQLite assigns a column from the type it was declared with.
 *
 * SQLite does not hold a column to its declared type. It reads the type name
 * for substrings and files the column under one of five affinities, so a
 * column declared VARCHAR2(9) and one declared TEXT behave identically, and a
 * column declared "POINT" gets numeric affinity because nothing in the name
 * matched anything else. The order the substrings are looked for is part of
 * the rule: INT wins over CHAR, so "INTCHAR" is an integer column.
 *
 * @see https://www.sqlite.org/datatype3.html#type_affinity
 */
enum SqliteAffinity
{
    case Integer;
    case Text;
    case Blob;
    case Real;
    case Numeric;

    /**
     * Answers the affinity SQLite gives a column declared with a type name.
     *
     * @param string $declaredType Type as the CREATE TABLE statement spells it
     *
     * @return self The affinity the column is stored under
     */
    public static function of(string $declaredType): self
    {
        $type = strtoupper($declaredType);
        if (str_contains($type, 'INT')) {
            return self::Integer;
        }
        if (str_contains($type, 'CHAR') || str_contains($type, 'CLOB') || str_contains($type, 'TEXT')) {
            return self::Text;
        }
        if ($type === '' || str_contains($type, 'BLOB')) {
            return self::Blob;
        }
        if (str_contains($type, 'REAL') || str_contains($type, 'FLOA') || str_contains($type, 'DOUB')) {
            return self::Real;
        }

        return self::Numeric;
    }
}
