<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\CommonTable;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithClause;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Resolution\CommonBinding;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Validation\ValueGraph;

/**
 * Binds the common table expressions of a WITH clause.
 *
 * Rule: SQLITE-COMMON-TABLE-001. Every table of the clause is visible in
 * every query of the clause and in the statement the clause belongs to,
 * whether or not RECURSIVE is written. A table is derived after the tables
 * of the clause it refers to; while its own query is derived it is bound to
 * its column list, with columns that can hold any storage class or NULL
 * (see SQLITE-COMPOUND-SCOPE-001 for recursive queries). The columns of a
 * table are the result columns of its query (SQLITE-RELATION-NAME-001),
 * renamed by the column list when one is written; a column list of another
 * length, a collation or direction in the list and a repeated table name are
 * reported. A table that refers to a table that refers back cannot be
 * ordered; SQLite rejects it, and the reference then falls through to the
 * declared relations. Terminates: each table is derived once.
 * Source: https://sqlite.org/lang_with.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CommonTables
{
    /**
     * Derives the tables and answers the environment in which they are visible.
     */
    public function bind(WithClause $with, Derivation $derivation, Environment $outer): Environment
    {
        $names = $derivation->context->relationNames;
        $uses = [];
        $seen = [];
        foreach ($with->tables as $index => $table) {
            $uses[$index] = $this->uses($table);
            if (isset($seen[$names->fold($table->name->value)])) {
                $derivation->report(new Misuse(MisuseRule::DuplicateCommonTable));
            }
            $seen[$names->fold($table->name->value)] = true;
        }
        $pending = $with->tables;
        $bindings = [];
        while ($pending !== []) {
            $next = array_key_first($pending);
            foreach ($pending as $index => $table) {
                $waiting = false;
                foreach ($pending as $other => $candidate) {
                    $waiting = $waiting || ($other !== $index && isset($uses[$index][$names->fold($candidate->name->value)]));
                }
                if (!$waiting) {
                    $next = $index;
                    break;
                }
            }
            $table = $pending[$next];
            unset($pending[$next]);
            $fact = $derivation->query($table->query, new Environment($derivation->context, $outer, [], [...$bindings, new CommonBinding($table->name, $table, $this->provisional($table))]));
            $bindings[] = new CommonBinding($table->name, $table, $this->shape($table, $fact, $derivation));
        }

        return new Environment($derivation->context, $outer, [], $bindings);
    }

    /**
     * Answers the unqualified table names the query of a common table reads from, folded.
     *
     * @return array<string, true>
     */
    public function uses(CommonTable $table): array
    {
        $names = [];
        foreach ((new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', 'SqlSemantics\\Platform\\Sqlite\\Statement\\']))->objects($table->query) as $object) {
            if ($object instanceof TableInput && $object->name->schema === null) {
                $names[strtr($object->name->name->value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')] = true;
            }
        }

        return $names;
    }

    /**
     * Answers the shape a common table has while its own query is derived.
     */
    public function provisional(CommonTable $table): RowShape
    {
        $slots = [];
        foreach ($table->columns as $column) {
            $slots[] = new OutputSlot($column->name, new Choice(Storage::cases()), Nullability::Nullable);
        }

        return new RowShape($slots);
    }

    /**
     * Answers the shape of a common table from the output of its query and its column list.
     */
    public function shape(CommonTable $table, QueryFact $fact, Derivation $derivation): RowShape
    {
        $shape = (new RelationNames())->shape($fact);
        if ($table->columns === []) {
            return $shape;
        }
        if ($shape->complete() && count($shape->slots) !== count($table->columns)) {
            $derivation->report(new ArityMismatch(ArityRule::CommonTableColumns, count($table->columns), count($shape->slots)));
        }
        $slots = [];
        $decorated = false;
        foreach ($table->columns as $position => $column) {
            $slot = $shape->slots[$position] ?? null;
            $decorated = $decorated || $column->collation !== null || $column->direction !== null;
            $slots[] = $slot === null
                ? new OutputSlot($column->name, $shape->complete() ? new Choice(Storage::cases()) : new Dependent($shape->missing), $shape->complete() ? Nullability::Nullable : Nullability::Dependent)
                : new OutputSlot($column->name, $slot->type, $slot->nullability, null, $slot);
        }
        if ($decorated) {
            $derivation->report(new Misuse(MisuseRule::DecoratedColumnName));
        }

        return new RowShape($slots);
    }
}
