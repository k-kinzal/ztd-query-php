<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Names the columns a query result has when the query is used as a relation.
 *
 * Rule: SQLITE-RELATION-NAME-001. SQLite names the columns of a subquery in
 * FROM and of a common table before it resolves the query
 * (`sqlite3ColumnsFromExprList()` in select.c of release 3.47.2), from the
 * leftmost arm of a compound query. A column takes the alias of its result
 * column; without an alias, a result column that is one word (a column name
 * with or without qualifier, a double-quoted word or a truth word, possibly
 * in parentheses or under COLLATE) takes that word as written, whatever it
 * resolves to, and any other result column takes the span of its expression
 * (SQLITE-RESULT-NAME-001). A column a star contributes keeps the name of the
 * column it copies. An expression of a VALUES row is named by its word, or
 * `columnN` after its position when it is no word. A column that would be
 * named TRUE or FALSE is renamed `columnN`. A name that repeats an earlier
 * one, compared without regard to ASCII case, has its trailing `:digits`
 * replaced by `:1`, `:2`, `:3` and `:4` in turn; after the fourth attempt
 * SQLite picks the digits at random, so such a name is not fixed (a random
 * name is taken not to repeat a later one). After a star that missing
 * declarations prevent from expanding, or after a name that depends on
 * missing inputs, the positions or the earlier names are unknown, so no later
 * name is fixed: each depends on those inputs (OutputSlot::$unnamed). Every column refers to the result
 * column it comes from. Terminates: one pass over the fields with at most
 * four renames each.
 * Source: https://sqlite.org/lang_select.html#the_from_clause,
 * https://sqlite.org/c3ref/column_name.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class RelationNames
{
    /**
     * Answers the row shape a query result contributes as a relation.
     *
     * @param QueryFact $fact The output of the query
     * @param Query $query The query
     * @param Derivation $derivation The derivation that recorded the output of the arms of the query
     */
    public function shape(QueryFact $fact, Query $query, Derivation $derivation): RowShape
    {
        $names = new ResultNames();
        $arm = $names->leftmost($query);
        $sources = $arm === null || $arm === $query ? null : $derivation->facts()->query($arm)->fields();
        $slots = [];
        $seen = [];
        $pending = [];
        foreach ($fact->projection as $item) {
            if (!$item instanceof Field) {
                foreach ($item->missing as $input) {
                    $pending[spl_object_id($input)] = $input;
                }
                continue;
            }
            $position = count($slots);
            $source = $sources === null ? $item : $sources->at($position);
            $written = $pending !== [] || $arm === null ? null : $names->truth($this->named($source, $arm, $position), $position);
            $name = $this->unique($written, $seen);
            $unnamed = $pending !== [] ? array_values($pending) : ($written === null ? $source->slot->unnamed : []);
            if ($name !== null) {
                $seen[Comparison::AsciiInsensitive->fold($name->value)] = true;
            }
            foreach ($unnamed as $input) {
                $pending[spl_object_id($input)] = $input;
            }
            $slots[] = new OutputSlot($name, $item->type, $item->nullability, null, $item->slot, $unnamed);
        }

        return new RowShape($slots, $fact->shape->missing);
    }

    /**
     * Answers the name of a column of the leftmost arm before TRUE and FALSE are renamed and duplicates are told apart.
     *
     * @param Field $field The output field of the arm
     * @param int $position The position of the field, from zero
     */
    public function named(Field $field, Select|ValuesClause $arm, int $position): ?Name
    {
        $names = new ResultNames();
        if ($arm instanceof ValuesClause) {
            return $names->written($arm->rows[0]->values[$position] ?? null) ?? new Name('column' . ($position + 1));
        }
        $column = $names->column($arm, $field);

        return $column === null ? $field->name : $names->relation($column);
    }

    /**
     * Answers the names of a column list, as SQLite gives them to the columns of a common table.
     *
     * @param list<Name> $listed The names as written
     * @return list<Name|null>
     */
    public function listed(array $listed): array
    {
        $names = [];
        $seen = [];
        foreach ($listed as $position => $name) {
            $unique = $this->unique((new ResultNames())->truth($name, $position), $seen);
            if ($unique !== null) {
                $seen[Comparison::AsciiInsensitive->fold($unique->value)] = true;
            }
            $names[] = $unique;
        }

        return $names;
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
            $text = (preg_replace('/:[0-9]*\z/s', '', $text) ?? $text) . ':' . $attempt;
            if (!isset($seen[Comparison::AsciiInsensitive->fold($text)])) {
                return new Name($text);
            }
        }

        return null;
    }
}
