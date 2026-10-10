<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Aggregation as Aggregates;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
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
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field as OutputField;

/**
 * Resolves the types of the columns of a temporary table: the rows of a set operation and of a derived table the server materializes.
 *
 * A derived table merges into the query that reads it when it is a single query block that
 * reads tables without grouping, aggregating, removing duplicates, limiting or windowing; its
 * columns keep the types of their expressions. Otherwise it is materialized: a column keeps the
 * type of a column it copies; a BIGINT narrower than 10 characters becomes an INT, a string loses
 * its decimals, and NULL becomes an empty binary string. An integer column reports the length of
 * its expression as its display width, while an expression over it sees the length of its whole
 * type. Computed strings longer than 512 characters become BLOB columns with a separate
 * storage capacity, while copied columns retain their type. The rows of a set operation are materialized too, and a TEXT or BLOB column counts its
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
     * Resolves a materialized projection with the temporary-column rule of its expression.
     *
     * GROUP_CONCAT uses the session byte limit divided by the character set's minimum
     * width. Up to 512 characters the column is VARCHAR; beyond that it is BLOB and
     * holds the maximum byte bound. This differs from the function's directly reported
     * domain, including in MySQL 5.6 and 5.7. Verified through SQL with latin1, utf8mb4,
     * UCS-2 and UTF-16 on MySQL 5.6, 8.0 and 8.4.
     */
    public function projected(Domain $domain, Query $query, int $position, Derivation $derivation, bool $narrows = true): Domain
    {
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        $facts = $derivation->facts();
        $field = $query instanceof Select && $facts->covers($query) ? ($facts->query($query)->projection[$position] ?? null) : null;
        $expression = $field instanceof OutputField ? $field->expression : null;
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        $resolution = $expression !== null && $facts->covers($expression) ? $facts->scalar($expression)->resolution : ($field instanceof OutputField ? $field->resolution : null);
        $text = $domain->kind === Kind::String && in_array($domain->field, [Field::VarChar, Field::VarString, Field::String, Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob], true);
        if (!$text || !$query instanceof Select || $resolution instanceof ResolvedColumn) {
            return !$query instanceof Select ? $this->combined($domain, $narrows, $derivation->context->profile->grammar) : $this->column($domain, $narrows);
        }

        return $this->computed($domain, $expression, Settings::of($derivation->context), $derivation->context->profile->grammar);
    }

    /**
     * Resolves the temporary column of a computed string, retaining its storage capacity separately from its reported width.
     */
    public function computed(Domain $domain, ?Scalar $expression, Settings $settings, GrammarRelease $release): Domain
    {
        $width = $domain->collation->charset->maxLength;
        $characters = $expression instanceof GroupConcat ? intdiv($settings->groupConcatMaxLen, $domain->collation->charset->minLength()) : ($domain->field->blob() ? intdiv($domain->metadataLength(), $width) : $domain->length);
        if ($expression instanceof GroupConcat && $characters <= 512) {
            return new Domain(Kind::String, Field::VarString, $characters, 0, false, $domain->collation, [], $domain->coercibility);
        }
        if ($characters <= 512 && !$domain->field->blob()) {
            return $this->column($domain);
        }
        $bytes = min(4294967295, $characters * $width);
        $legacy = $release === GrammarRelease::MySql5651;

        return $this->stored($domain, $bytes, $legacy ? $characters : $bytes);
    }

    /**
     * Resolves a temporary BLOB column with its reported byte bound and storage capacity.
     * Expressions over the column see the whole storage type; direct projection
     * retains the original bound. The allocation bound is in characters in 5.6
     * and bytes in later releases, as observed with materialized casts.
     */
    public function stored(Domain $domain, int $bytes, int $allocation): Domain
    {
        $capacity = $allocation <= 255 ? 255 : ($allocation <= 65535 ? 65535 : ($allocation <= 16777215 ? 16777215 : 4294967295));

        return new Domain(Kind::String, Field::Blob, $bytes, 0, false, $domain->collation, [], $domain->coercibility, null, $capacity);
    }

    /**
     * Gives a derived set result its storage capacity. A legacy set result keeps its
     * expression bound until a derived table reads it; later releases already retain
     * the temporary column capacity in the set output.
     */
    public function combined(Domain $domain, bool $narrows = true, GrammarRelease $release = GrammarRelease::MySql847): Domain
    {
        $allocation = $release === GrammarRelease::MySql5651 ? intdiv($domain->length, $domain->collation->charset->maxLength) : $domain->length;

        return $domain->field === Field::Blob && $domain->capacity === null ? $this->stored($domain, $domain->length, $allocation) : $this->column($domain, $narrows);
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

        $length = $domain->field === Field::Blob ? min(4294967295, $domain->length * $domain->collation->charset->maxLength) : $domain->length;
        $legacy = $release === GrammarRelease::MySql5651;

        return $domain->field === Field::Blob && !$legacy ? $this->stored($domain, $length, $length) : $this->text($domain, $length);
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
        return new Domain(Kind::String, $domain->field, $length, 0, false, $domain->collation, $domain->members, $domain->coercibility, null, $domain->capacity);
    }

    /**
     * Answers the empty binary string a NULL column becomes, a temporal value in a character set binary again, and any other column as it is.
     */
    public function nothing(Domain $domain): Domain
    {
        if ($domain->kind->temporal() && !$domain->collation->bytes()) {
            return new Domain($domain->kind, $domain->field, $domain->length, $domain->decimals, false, null, [], $domain->coercibility);
        }

        return $domain->kind === Kind::Null ? new Domain(Kind::String, Field::VarString, 0, 0, false, Collation::binary(), [], Coercibility::Ignorable) : $domain;
    }
}
