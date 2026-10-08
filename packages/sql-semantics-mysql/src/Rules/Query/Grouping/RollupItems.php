<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Grouping;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field as FieldType;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the output fields of a query block that groups WITH ROLLUP.
 *
 * Rule: MYSQL-ROLLUP-ITEMS-001. A select item that is a grouping
 * expression, written alike or named by its alias or position
 * (MYSQL-ONLY-FULL-GROUP-BY-001), is a rollup item: it is NULL in the
 * super-aggregate rows and can be NULL; the server reports no base table
 * for it. Sent as the
 * block answers it, a rollup item keeps its type, except that a string
 * and spatial value has no fixed decimals, a TEXT or BLOB counts the
 * bytes of its characters as characters, a temporal value is text in the
 * connection collation, and a JSON value is text in the connection
 * collation of as many characters as its bytes hold in utf8mb4. When the rows of the block pass through a
 * temporary table, for ORDER BY, DISTINCT or a materialized derived table,
 * a rollup item takes the column the table gives it: an integer, BIT or
 * YEAR becomes an INT up to 9 digits and a BIGINT beyond, keeping its sign;
 * a string other than TEXT or BLOB, and a TINYTEXT or TINYBLOB, becomes a
 * VARCHAR or VARBINARY of its characters; a temporal value is binary
 * again, and a JSON value is a JSON column. Any other select item that reads a grouping expression outside
 * aggregates can be NULL; one that does not keeps the NULL fact it has
 * before grouping. Verified on a live 8.4 server. Terminates: one pass over
 * the select list, each expression walked once.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/group-by-modifiers.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RollupItems
{
    /**
     * Answers the select list of a block WITH ROLLUP, with the types and NULL facts of its rows; the list of any other block as it is.
     *
     * @param list<Field|OpenStar> $items The fields derived where every column of the block can be NULL
     * @param list<VisibleRelation> $visible The relations of the FROM clause before grouping
     * @param list<VisibleRelation> $output The same relations whose columns can be NULL
     * @return list<Field|OpenStar>
     */
    public function fields(Select $select, array $items, array $visible, array $output, Derivation $derivation): array
    {
        if (!$this->rolls($select)) {
            return $items;
        }
        $facts = $derivation->facts();
        $groups = $this->groups($select, $facts);
        $materialized = $select->orderBy !== [] || ($select->late !== null && $select->late->orderBy !== []) || in_array(SelectOption::Distinct, $select->options, true);
        $connection = Settings::of($derivation->context)->connection;
        $fields = [];
        foreach ($items as $field) {
            if (!$field instanceof Field) {
                $fields[] = $field;
                continue;
            }
            $slot = $field->slot;
            if ($this->rolled($field, $groups, $facts)) {
                $domain = $slot->type instanceof Known && $slot->type->descriptor instanceof Domain ? $slot->type->descriptor : null;
                $type = $domain === null ? $slot->type : new Known($materialized ? $this->materialized($this->item($domain, $connection)) : $this->item($domain, $connection));
                $fields[] = new Field($field->position, new OutputSlot($slot->name, $type, Nullability::Nullable, $slot->column, $slot->origin, $slot->unnamed), $field->expression, $field->resolution);
                continue;
            }
            $nullability = $field->expression !== null && $this->reads($field->expression, $groups, $facts) ? Nullability::Nullable : $this->before($field, $visible, $output, $facts);
            $fields[] = new Field($field->position, new OutputSlot($slot->name, $slot->type, $nullability, $slot->column, $slot->origin, $slot->unnamed), $field->expression, $field->resolution);
        }

        return $fields;
    }

    /**
     * Tells whether a block groups WITH ROLLUP.
     */
    public function rolls(Select $select): bool
    {
        return $select->groupBy !== null && $select->groupBy->modifier !== null;
    }

    /**
     * Answers the grouping expressions of a block, each the select item its alias or position names, without parentheses.
     *
     * @return list<Scalar>
     */
    public function groups(Select $select, Facts $facts): array
    {
        $matching = new Matching();
        $groups = [];
        foreach ($select->groupBy->items ?? [] as $item) {
            $groups[] = $matching->unwrap($matching->target($item->expression, $facts));
        }

        return $groups;
    }

    /**
     * Tells whether a field is a grouping expression: its expression is one, or it is a column of a star that one names.
     *
     * @param list<Scalar> $groups
     */
    public function rolled(Field $field, array $groups, Facts $facts): bool
    {
        $reads = new ColumnReads();
        if ($field->expression !== null) {
            return (new Matching())->listed($field->expression, $groups, $facts);
        }
        if (!$field->resolution instanceof ResolvedColumn) {
            return false;
        }
        $key = $reads->key($field->resolution);
        foreach ($groups as $group) {
            if ($group instanceof ColumnUse && $facts->covers($group)) {
                $resolution = $facts->scalar($group)->resolution;
                if ($resolution instanceof ResolvedColumn && $reads->key($resolution) === $key) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Tells whether an expression reads a grouping expression outside aggregates and subqueries.
     *
     * @param list<Scalar> $groups
     */
    public function reads(Node $node, array $groups, Facts $facts): bool
    {
        if ($node instanceof Query || $node instanceof Aggregate && $node->over === null || $node instanceof GroupConcat && $node->over === null || $node instanceof JsonObjectAggregate || $node instanceof KeywordCall && $node->function === KeywordFunction::Grouping) {
            return false;
        }
        if ($node instanceof Scalar && (new Matching())->listed($node, $groups, $facts)) {
            return true;
        }
        $properties = get_object_vars($node);
        $children = [];
        array_walk_recursive($properties, static function ($value) use (&$children): void {
            if ($value instanceof Node) {
                $children[] = $value;
            }
        });
        foreach ($children as $child) {
            if ($this->reads($child, $groups, $facts)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the NULL fact a field that reads no grouping expression has: for a column of the block, that of the column before grouping; else the one derived.
     *
     * @param list<VisibleRelation> $visible
     * @param list<VisibleRelation> $output
     */
    public function before(Field $field, array $visible, array $output, Facts $facts): Nullability
    {
        $resolution = $field->resolution;
        $expression = $field->expression === null ? null : (new Matching())->unwrap($field->expression);
        if ($expression instanceof ColumnUse && $facts->covers($expression)) {
            $resolution = $facts->scalar($expression)->resolution;
        }
        if (!$resolution instanceof ResolvedColumn) {
            return $field->nullability;
        }
        foreach ($output as $index => $relation) {
            $position = array_search($resolution->slot, $relation->shape->slots, true);
            if ($relation->relation === $resolution->relation && is_int($position) && isset($visible[$index]->shape->slots[$position])) {
                return $visible[$index]->shape->slots[$position]->nullability;
            }
        }

        return $field->nullability;
    }

    /**
     * Answers the type of a rollup item as the block answers it.
     */
    public function item(Domain $domain, Collation $connection): Domain
    {
        if ($domain->kind === Kind::Json) {
            return new Domain(Kind::Json, $domain->field, intdiv($domain->length, Charset::known('utf8mb4')->maxLength), Domain::NOT_FIXED, false, $connection, [], $domain->coercibility);
        }
        if ($domain->kind === Kind::String) {
            $length = $domain->field === FieldType::Blob ? min(4294967295, $domain->length * $domain->collation->charset->maxLength) : $domain->length;

            return new Domain($domain->kind, $domain->field, $length, Domain::NOT_FIXED, $domain->unsigned, $domain->collation, $domain->members, $domain->coercibility);
        }
        if ($domain->kind->temporal()) {
            return new Domain($domain->kind, $domain->field, $domain->length, $domain->decimals, false, $connection, [], $domain->coercibility);
        }

        return $domain;
    }

    /**
     * Answers the type of a rollup item once a temporary table holds it.
     */
    public function materialized(Domain $domain): Domain
    {
        if ($domain->kind === Kind::Integer || $domain->kind === Kind::Bit || $domain->kind === Kind::Year) {
            $unsigned = $domain->unsigned || $domain->kind !== Kind::Integer;

            return Domain::integer($domain->length > 9 ? FieldType::LongLong : FieldType::Long, $domain->length, $unsigned);
        }
        if ($domain->kind->temporal()) {
            return new Domain($domain->kind, $domain->field, $domain->length, $domain->decimals, false, null, [], $domain->coercibility);
        }
        if ($domain->kind === Kind::Json) {
            return new Domain(Kind::Json, $domain->field, 4294967295, 0, false, null, [], $domain->coercibility);
        }
        if ($domain->kind !== Kind::String) {
            return $domain;
        }
        $characters = $domain->field === FieldType::Blob ? intdiv($domain->length, $domain->collation->charset->maxLength) : $domain->length;
        if ($domain->field === FieldType::Blob && $characters > 255) {
            return new Domain(Kind::String, FieldType::Blob, $domain->length, 0, false, $domain->collation, [], $domain->coercibility);
        }

        return new Domain(Kind::String, FieldType::VarString, $characters, 0, false, $domain->collation, [], $domain->coercibility);
    }

    /**
     * Tells whether the column at a position of a query a temporary table holds is a rollup item: the query is a block WITH ROLLUP whose field at that position is a grouping expression.
     */
    public function rolledAt(Query $query, int $position, Derivation $derivation): bool
    {
        while ($query instanceof ParenthesizedQuery || ($query instanceof QueryExpression && $query->with === null && $query->orderBy === [] && $query->limit === null)) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        if (!$query instanceof Select || !$this->rolls($query)) {
            return false;
        }
        $facts = $derivation->facts();
        if (!$facts->covers($query)) {
            return false;
        }
        $field = $facts->query($query)->projection[$position] ?? null;

        return $field instanceof Field && $this->rolled($field, $this->groups($query, $facts), $facts);
    }
}
