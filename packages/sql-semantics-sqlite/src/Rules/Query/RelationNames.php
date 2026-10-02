<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Query;

use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Names the columns a query result has when the query is used as a relation.
 *
 * Rule: SQLITE-RELATION-NAME-001. A column takes the name of its result
 * column; a result column without a fixed name that denotes a column (a
 * collated column use) takes the name of that column. A column that would be
 * named TRUE or FALSE is renamed `columnN` after its position. A name that
 * repeats an earlier one gets the suffix `:1`, `:2`, and so on; SQLite picks
 * a random suffix after the fourth attempt, so such a name is not fixed. A
 * column whose name SQLite takes from source text stays without a fixed
 * name. Every column refers to the result column it comes from.
 * Source: https://sqlite.org/lang_select.html#the_from_clause,
 * https://sqlite.org/c3ref/column_name.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class RelationNames
{
    /**
     * Answers the row shape a query result contributes as a relation.
     */
    public function shape(QueryFact $fact): RowShape
    {
        $slots = [];
        $seen = [];
        foreach ($fact->projection as $item) {
            if (!$item instanceof Field) {
                continue;
            }
            $name = $this->unique($this->named($item, count($slots)), $seen);
            if ($name !== null) {
                $seen[Comparison::AsciiInsensitive->fold($name->value)] = true;
            }
            $slots[] = new OutputSlot($name, $item->type, $item->nullability, null, $item->slot);
        }

        return new RowShape($slots, $fact->shape->missing);
    }

    /**
     * Answers the name of a result column before duplicates are told apart.
     */
    public function named(Field $field, int $position): ?Name
    {
        $name = $field->name ?? ($field->resolution instanceof ResolvedColumn ? $field->resolution->slot->name : null);
        if ($name !== null && in_array(Comparison::AsciiInsensitive->fold($name->value), ['true', 'false'], true)) {
            return new Name('column' . ($position + 1));
        }

        return $name;
    }

    /**
     * Answers a name that repeats none of the names seen, or null when SQLite would pick it at random.
     *
     * @param array<string, true> $seen The names already used, folded
     */
    public function unique(?Name $name, array $seen): ?Name
    {
        if ($name === null || !isset($seen[Comparison::AsciiInsensitive->fold($name->value)])) {
            return $name;
        }
        $text = $name->value;
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $text = (preg_replace('/(?<=.):[0-9]*\z/s', '', $text) ?? $text) . ':' . $attempt;
            if (!isset($seen[Comparison::AsciiInsensitive->fold($text)])) {
                return new Name($text);
            }
        }

        return null;
    }
}
