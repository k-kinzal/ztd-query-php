<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Answers the domain of a column of a materialized result: a derived table, a common table expression, or a set operation.
 *
 * The server writes such a result into a temporary table, and a column read from it has the
 * type of the field created for it: an integer expression shorter than eleven characters becomes
 * an INT, a string has no decimals, and NULL becomes an empty binary string.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/internal-temporary-tables.html.
 *
 * @visibility MySqlMemory
 */
final class Materialized
{
    /**
     * Answers the domain of a column written to a temporary table.
     */
    public static function column(Domain $domain): Domain
    {
        if ($domain->kind === Kind::Integer && $domain->field === Field::LongLong && $domain->length < 11) {
            return new Domain(Kind::Integer, Field::Long, $domain->length, 0, $domain->unsigned, $domain->collation, $domain->nullable, [], $domain->coercibility);
        }
        if ($domain->kind === Kind::String) {
            return new Domain(Kind::String, $domain->field, $domain->length, 0, false, $domain->collation, $domain->nullable, $domain->members, $domain->coercibility);
        }

        return self::set($domain);
    }

    /**
     * Answers the domain of a column of a merged derived table: a date or time reports the length of its text in the connection character set.
     */
    public static function merged(Domain $domain, Collation $connection): Domain
    {
        if (!$domain->kind->temporal()) {
            return $domain;
        }

        return $domain->withCollation($connection, $domain->coercibility);
    }

    /**
     * Answers the domain of a column of a set operation: strings without decimals, NULL as an empty binary string.
     */
    public static function set(Domain $domain): Domain
    {
        if ($domain->kind === Kind::Null) {
            return new Domain(Kind::String, Field::VarString, 0, 0, false, Collation::binary(), true, [], Coercibility::Ignorable);
        }
        if ($domain->kind === Kind::String) {
            $length = $domain->field === Field::Blob ? min(4294967295, $domain->length * $domain->collation->charset->maxLength) : $domain->length;

            return new Domain(Kind::String, $domain->field, $length, 0, false, $domain->collation, $domain->nullable, $domain->members, $domain->coercibility);
        }

        return $domain;
    }
}
