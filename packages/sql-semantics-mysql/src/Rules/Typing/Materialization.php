<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Aggregation as Aggregates;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Query;

/**
 * Resolves the types of the columns of a temporary table: the rows of a set operation and of a derived table the server materializes.
 *
 * A derived table merges into the query that reads it when it is a single query block that
 * reads tables without grouping, aggregating, removing duplicates, limiting or windowing; its
 * columns keep the types of their expressions. Otherwise it is materialized: a column keeps the
 * type of a column it copies; a BIGINT narrower than 10 characters becomes an INT, a string loses
 * its decimals, and NULL becomes an empty binary string. An integer column reports the length of
 * its expression as its display width, while an expression over it sees the length of its whole
 * type. The rows of a set operation are materialized too, and a TEXT or BLOB column counts its
 * length in bytes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/derived-table-optimization.html,
 * https://dev.mysql.com/doc/refman/8.4/en/numeric-type-attributes.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Materialization
{
    /**
     * Tells whether the server merges a derived query into the query that reads it instead of materializing it.
     */
    public function mergeable(Query $query): bool
    {
        while ($query instanceof ParenthesizedQuery || ($query instanceof QueryExpression && $query->with === null && $query->orderBy === [] && $query->limit === null)) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        if (!$query instanceof Select || $query->from === null || $query->from instanceof Dual) {
            return false;
        }
        if ($query->groupBy !== null || $query->having !== null || $query->limit !== null || $query->windows !== [] || in_array(SelectOption::Distinct, $query->options, true)) {
            return false;
        }
        $parts = array_map(static fn (object $item): object => $item instanceof SelectExpression ? $item->expression : $item, $query->items);

        return !(new Aggregates())->aggregates([...$parts, ...array_map(static fn ($item): object => $item->expression, $query->orderBy)]);
    }

    /**
     * Tells whether the columns of a materialized query take the narrowest integer type their values fit: those of a single query block or row, not of several VALUES rows or a set operation, whose columns settle on their type first.
     */
    public function narrows(Query $query): bool
    {
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }

        return $query instanceof Select || ($query instanceof ValuesQuery && count($query->rows) === 1);
    }

    /**
     * Resolves a column of a materialized derived table, narrowing a short BIGINT to an INT when its query narrows.
     */
    public function column(Domain $domain, bool $narrows = true): Domain
    {
        if ($domain->kind === Kind::Integer && $domain->display === null) {
            $narrowed = $narrows && $domain->field === Field::LongLong && $domain->length < 10;

            return Domain::column($narrowed ? Field::Long : $domain->field, $domain->length, $domain->unsigned);
        }

        return $domain->kind === Kind::String ? $this->text($domain, $domain->length) : $this->nothing($domain);
    }

    /**
     * Resolves a column of the rows of a set operation.
     *
     * A column that is NULL in every operand is an empty binary string: before MySQL 8.1, which
     * simplified how the type of several values is aggregated (Bug #34847836), a CHAR, from 8.1 a
     * VARCHAR (verified on live 5.6, 5.7, 8.0, 8.4 and 9.1 servers).
     * Source: https://dev.mysql.com/doc/relnotes/mysql/8.1/en/news-8-1-0.html.
     */
    public function set(Domain $domain, GrammarRelease $release = GrammarRelease::MySql847): Domain
    {
        if ($domain->kind === Kind::Null && in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044], true)) {
            return new Domain(Kind::String, Field::String, 0, 0, false, Collation::binary(), [], Coercibility::Ignorable);
        }
        if ($domain->kind !== Kind::String) {
            return $this->nothing($domain);
        }

        return $this->text($domain, $domain->field === Field::Blob ? min(4294967295, $domain->length * $domain->collation->charset->maxLength) : $domain->length);
    }

    /**
     * Resolves the value a window function reads from the temporary table of its window: MIN, MAX, FIRST_VALUE, LAST_VALUE, NTH_VALUE, LEAD and LAG answer it as the table holds it.
     *
     * An integer, a YEAR and a BIT become an INT when shorter than 10 characters and a BIGINT
     * otherwise, as long as the value; a string a VARCHAR without decimals, a TEXT or BLOB of any
     * size, or a string longer than 65535, a BLOB as long as its bytes; JSON the longest JSON
     * without decimals; a temporal value takes the length of its type and
     * fractional digits; NULL an empty binary string, a CHAR before MySQL 8.1 as in a set
     * operation (verified on live 8.0 and 8.4 servers).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-optimization.html.
     */
    public function windowed(Domain $domain, GrammarRelease $release = GrammarRelease::MySql847): Domain
    {
        $fraction = $domain->decimals > 0 && $domain->decimals <= 6 ? $domain->decimals + 1 : 0;

        return match ($domain->kind) {
            Kind::Integer, Kind::Year, Kind::Bit => new Domain(Kind::Integer, $domain->length < 10 ? Field::Long : Field::LongLong, $domain->length, 0, $domain->unsigned, null, [], Coercibility::Numeric),
            Kind::String => ($domain->field->blob() || $domain->length > 65535
                ? new Domain(Kind::String, Field::Blob, min(4294967295, $domain->length * ($domain->field->blob() ? $domain->collation->charset->maxLength : 1)), 0, false, $domain->collation, [], $domain->coercibility)
                : new Domain(Kind::String, Field::VarString, $domain->length, 0, false, $domain->collation, [], $domain->coercibility)),
            Kind::Date => new Domain(Kind::Date, $domain->field, 10, 0, false, null, [], $domain->coercibility),
            Kind::DateTime => new Domain(Kind::DateTime, $domain->field, 19 + $fraction, $domain->decimals, false, null, [], $domain->coercibility),
            Kind::Time => new Domain(Kind::Time, $domain->field, 10 + $fraction, $domain->decimals, false, null, [], $domain->coercibility),
            Kind::Json => new Domain(Kind::Json, Field::Json, 4294967295, 0, false, Collation::binary(), [], $domain->coercibility),
            Kind::Null => $this->set($domain, $release),
            Kind::Decimal, Kind::Double => $domain,
        };
    }

    /**
     * Answers a string column of a length without decimals.
     */
    public function text(Domain $domain, int $length): Domain
    {
        return new Domain(Kind::String, $domain->field, $length, 0, false, $domain->collation, $domain->members, $domain->coercibility);
    }

    /**
     * Answers the empty binary string a NULL column becomes, and any other column as it is.
     */
    public function nothing(Domain $domain): Domain
    {
        return $domain->kind === Kind::Null ? new Domain(Kind::String, Field::VarString, 0, 0, false, Collation::binary(), [], Coercibility::Ignorable) : $domain;
    }
}
