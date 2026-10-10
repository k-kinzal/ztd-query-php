<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\RollupItems;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the row shape of a derived table or a common table from its query and its column list.
 *
 * Rule: MYSQL-DERIVED-SHAPES-001. Without a column list the columns are the
 * output slots of the query, with their names (MYSQL-SELECT-ITEM-NAME-001);
 * a slot whose name depends on missing inputs keeps them as its unnamed
 * inputs, so that a column lookup in the relation depends on them
 * (CORE-COLUMN-LOOKUP-001). With a column list the list names the
 * columns in order and the query gives their types, which depend on the
 * missing inputs of the query while its columns are not all known; a list whose length
 * differs from a complete query is reported, and a column without a slot is
 * invalid. Duplicate column names are reported. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/derived-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/with.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DerivedShapes
{
    /**
     * Answers the shape of the rows the query produces under the given column names.
     *
     * @param list<Name> $columns The column list; empty when the query names the columns
     * @param Query|null $materialized The query when the server materializes its rows in a temporary table, whose columns take the types of that table
     */
    public function shape(QueryFact $fact, array $columns, Derivation $derivation, ?Query $materialized = null): RowShape
    {
        $shape = $materialized === null ? $this->merged($fact->shape, Settings::of($derivation->context)->connection) : $this->materialized($fact->shape, (new Materialization())->narrows($materialized), $materialized, $derivation);
        if ($columns === []) {
            $slots = [];
            foreach ($shape->slots as $slot) {
                $slots[] = new OutputSlot($slot->name, $slot->type, $slot->nullability, null, $slot, $slot->unnamed);
            }
            $this->unique($slots, $derivation);

            return new RowShape($slots, $shape->missing);
        }
        $problem = null;
        if ($shape->complete() && count($shape->slots) !== count($columns)) {
            $problem = new CountMismatch(CountedList::DerivedColumns, count($columns), count($shape->slots));
            $derivation->report($problem);
        }
        $slots = [];
        foreach ($columns as $position => $column) {
            $slot = $shape->complete() ? $shape->slots[$position] ?? null : null;
            if ($slot !== null) {
                $slots[] = new OutputSlot($column, $slot->type, $slot->nullability, null, $slot);
            } elseif ($problem !== null) {
                $slots[] = new OutputSlot($column, new Invalid($problem), Nullability::Dependent);
            } else {
                $slots[] = new OutputSlot($column, new Dependent($shape->missing), Nullability::Dependent);
            }
        }
        $this->unique($slots, $derivation);

        return new RowShape($slots);
    }

    /**
     * Answers a shape with the types a temporary table gives its columns; a rollup item of the query takes the column MYSQL-ROLLUP-ITEMS-001 gives it.
     */
    public function materialized(RowShape $shape, bool $narrows, ?Query $query = null, ?Derivation $derivation = null): RowShape
    {
        $slots = [];
        foreach ($shape->slots as $position => $slot) {
            $domain = (new Precision())->domain($slot->type);
            if ($domain !== null && $query !== null && $derivation !== null && (new RollupItems())->rolledAt($query, $position, $derivation)) {
                $domain = (new RollupItems())->materialized($domain);
                $slots[] = new OutputSlot($slot->name, new Known($domain), $slot->nullability, $slot->column, $slot->origin, $slot->unnamed);
                continue;
            }
            $slots[] = $domain === null ? $slot : new OutputSlot($slot->name, new Known((new Materialization())->column($domain, $narrows)), $slot->nullability, $slot->column, $slot->origin, $slot->unnamed);
        }

        return new RowShape($slots, $shape->missing);
    }

    /**
     * Answers the shape of a merged query as the query that reads it sees it: a temporal value other than YEAR is text in the connection collation.
     *
     * The server reads a column of a merged derived table, common table expression or view
     * through a reference that carries the connection collation, so the column and the
     * expressions over it are sent as text of that collation (MYSQL-ROLLUP-ITEMS-001 sends a
     * rollup item alike). Verified on live 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers.
     */
    public function merged(RowShape $shape, Collation $connection): RowShape
    {
        $slots = [];
        foreach ($shape->slots as $slot) {
            $domain = $slot->type instanceof Known && $slot->type->descriptor instanceof Domain ? $slot->type->descriptor : null;
            if ($domain === null || !$domain->kind->temporal() || !$domain->collation->bytes()) {
                $slots[] = $slot;
                continue;
            }
            $slots[] = new OutputSlot($slot->name, new Known(new Domain($domain->kind, $domain->field, $domain->length, $domain->decimals, false, $connection, [], $domain->coercibility)), $slot->nullability, $slot->column, $slot->origin, $slot->unnamed);
        }

        return new RowShape($slots, $shape->missing);
    }

    /**
     * Reports a column name that occurs twice.
     *
     * @param list<OutputSlot> $slots
     */
    public function unique(array $slots, Derivation $derivation): void
    {
        $seen = [];
        foreach ($slots as $slot) {
            if ($slot->name === null) {
                continue;
            }
            $key = $derivation->context->columnNames->fold($slot->name->value);
            if (isset($seen[$key])) {
                $derivation->report(new Misuse(MisuseRule::DuplicateColumn, $slot->name));

                return;
            }
            $seen[$key] = true;
        }
    }
}
