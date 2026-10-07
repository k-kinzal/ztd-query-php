<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Rules\Query\Aggregation as Aggregates;
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
 * columns keep the types of their expressions. Otherwise it is materialized: a BIGINT narrower
 * than 11 digits becomes an INT, a string loses its decimals, and NULL becomes an empty binary
 * string. The rows of a set operation are materialized too, and a TEXT or BLOB column counts its
 * length in bytes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/derived-table-optimization.html.
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
        if ($narrows && $domain->kind === Kind::Integer && $domain->field === Field::LongLong && $domain->length < 11) {
            return new Domain(Kind::Integer, Field::Long, $domain->length, 0, $domain->unsigned, null, [], $domain->coercibility);
        }

        return $domain->kind === Kind::String ? $this->text($domain, $domain->length) : $this->nothing($domain);
    }

    /**
     * Resolves a column of the rows of a set operation.
     */
    public function set(Domain $domain): Domain
    {
        if ($domain->kind !== Kind::String) {
            return $this->nothing($domain);
        }

        return $this->text($domain, $domain->field === Field::Blob ? min(4294967295, $domain->length * $domain->collation->charset->maxLength) : $domain->length);
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
