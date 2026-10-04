<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Query;

use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Names the columns a query result has when the query is used as a relation.
 *
 * Rule: SQLITE-RELATION-NAME-001. SQLite names these columns before it
 * resolves the query. A column takes the alias of its result column; without
 * an alias, a result column that is one word (a column name with or without
 * qualifier, a double-quoted word or a truth word, possibly in parentheses
 * or under COLLATE) takes that word as written, whatever it resolves to. A
 * column that would be named TRUE or FALSE is renamed `columnN` after its
 * position. A name that
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
     *
     * The field name is the alias when it is not the name the projection
     * derived from the expression; an alias wins over the written word.
     */
    public function named(Field $field, int $position): ?Name
    {
        $written = $this->written($field->expression);
        $derived = $field->name === null || $field->name === $written
            || ($field->resolution instanceof ResolvedColumn && $field->name === $field->resolution->slot->name)
            || ($field->resolution instanceof AliasTarget && $field->name === $field->resolution->field->name);
        $name = $written !== null && $derived ? $written : $field->name;
        if ($name !== null && in_array(Comparison::AsciiInsensitive->fold($name->value), ['true', 'false'], true)) {
            return new Name('column' . ($position + 1));
        }

        return $name;
    }

    /**
     * Answers the word a result expression is written as, when it is one word: a column use, a double-quoted word or a truth word, possibly in parentheses or under COLLATE.
     */
    public function written(?Scalar $expression): ?Name
    {
        while ($expression instanceof Grouped || $expression instanceof Collate) {
            $expression = $expression->operand;
        }
        if ($expression instanceof ColumnUse) {
            return $expression->name;
        }
        if ($expression instanceof DoubleQuotedWord) {
            return $expression->word;
        }

        return $expression instanceof TruthWord ? new Name($expression->value ? 'true' : 'false') : null;
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
