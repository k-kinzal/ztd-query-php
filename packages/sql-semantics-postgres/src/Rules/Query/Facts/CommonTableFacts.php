<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query\Facts;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Unification;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Carriers;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\ColumnAliases;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\SearchOrder;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\WithClause;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Resolution\CommonBinding;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Validation\ValueGraph;

/**
 * Binds the common table expressions of a WITH clause.
 *
 * Rule: PG-COMMON-TABLE-001. Without RECURSIVE a common table is visible to
 * the common tables after it and to the statement; with RECURSIVE it is
 * visible to every common table of the clause, which are derived after the
 * tables they refer to, and to itself. A table that refers to itself must be
 * a UNION of a non-recursive and a recursive part (PG-SET-OPERATION-001
 * binds the recursive part); two tables that refer to each other are
 * reported. The columns of a table are the output columns of its statement,
 * renamed by its column list; more names than columns are reported. SEARCH
 * adds its sequence column (a record, an array of records for depth-first)
 * and CYCLE its mark column (boolean, or the common type of the TO and
 * DEFAULT values, which see no column) and path column (an array of
 * records); their columns must be columns of the table, and both require a
 * recursive table. A repeated name is reported. The bindings join the
 * environment of the holder at its own query level. Terminates: each table
 * is derived once.
 * Source: https://www.postgresql.org/docs/17/queries-with.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CommonTableFacts
{
    /**
     * Derives the tables and answers the environment in which they are visible.
     */
    public function bind(WithClause $with, Derivation $derivation, Environment $outer): Environment
    {
        $names = $derivation->context->relationNames;
        $seen = [];
        $uses = [];
        foreach ($with->tables as $index => $table) {
            $key = $names->fold($table->name->value);
            if (isset($seen[$key])) {
                $derivation->report(new QueryMisuse(QueryMisuseRule::DuplicateCommonTable, $table->name));
            }
            $seen[$key] = true;
            $uses[$index] = $with->recursive ? $this->uses($table, $derivation) : [];
        }
        $bindings = [];
        foreach ($this->order($with, $uses, $derivation) as $index) {
            $table = $with->tables[$index];
            $recursive = isset($uses[$index][$names->fold($table->name->value)]);
            $provisional = $recursive ? [new CommonBinding($table->name, $table, new RowShape([]))] : [];
            if ($recursive && !$this->recursiveForm($table)) {
                $derivation->report(new QueryMisuse(QueryMisuseRule::RecursiveForm, $table->name));
            }
            if (!$recursive && ($table->search !== null || $table->cycle !== null)) {
                $derivation->report(new QueryMisuse(QueryMisuseRule::SearchOrCycleNotRecursive, $table->name));
            }
            $fact = $derivation->query($table->query, $this->level($outer, [...$bindings, ...$provisional]));
            $bindings[] = new CommonBinding($table->name, $table, $this->shape($table, $fact, $derivation));
        }

        return $this->level($outer, $bindings);
    }

    /**
     * Answers an environment with more common tables at the same query level.
     *
     * @param list<CommonBinding> $bindings
     */
    public function level(Environment $outer, array $bindings): Environment
    {
        return new Environment($outer->context, $outer->outer, $outer->relations, [...$outer->commonTables, ...$bindings], $outer->aliases);
    }

    /**
     * Answers the folded names of the unqualified relations a table's statement reads.
     *
     * @return array<string, true>
     */
    public function uses(CommonTableExpression $table, Derivation $derivation): array
    {
        $names = [];
        foreach ((new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', 'SqlSemantics\\Platform\\PostgreSql\\Statement\\']))->objects($table->query) as $object) {
            if ($object instanceof TableInput && $object->table->name->schema === null) {
                $names[$derivation->context->relationNames->fold($object->table->name->name->value)] = true;
            }
        }

        return $names;
    }

    /**
     * Answers the order the tables are derived in: written order, or with RECURSIVE every table after the tables it refers to.
     *
     * @param array<int, array<string, true>> $uses
     * @return list<int>
     */
    public function order(WithClause $with, array $uses, Derivation $derivation): array
    {
        $names = $derivation->context->relationNames;
        $pending = array_keys($with->tables);
        $order = [];
        $reported = false;
        while ($pending !== []) {
            $next = null;
            foreach ($pending as $index) {
                $waiting = false;
                foreach ($pending as $other) {
                    $waiting = $waiting || ($other !== $index && isset($uses[$index][$names->fold($with->tables[$other]->name->value)]));
                }
                if (!$waiting) {
                    $next = $index;
                    break;
                }
            }
            if ($next === null) {
                $next = $pending[0];
                if (!$reported) {
                    $derivation->report(new QueryMisuse(QueryMisuseRule::MutualRecursion));
                    $reported = true;
                }
            }
            $order[] = $next;
            $pending = array_values(array_diff($pending, [$next]));
        }

        return $order;
    }

    /**
     * Tells whether a recursive table has the form non-recursive part UNION [ALL] recursive part.
     */
    public function recursiveForm(CommonTableExpression $table): bool
    {
        $core = (new Carriers())->core($table->query);

        return $core instanceof SetOperation && $core->operator === SetOperator::Union;
    }

    /**
     * Answers the columns of a table: the output of its statement renamed by its column list, then the SEARCH and CYCLE columns.
     */
    public function shape(CommonTableExpression $table, QueryFact $fact, Derivation $derivation): RowShape
    {
        $base = (new ColumnAliases())->fromQuery($fact);
        if ($base->complete() && count($table->columns) > count($base->slots)) {
            $derivation->report(new ArityMismatch(ArityRule::CommonTableColumns, $table->name->value, count($base->slots), count($table->columns)));
        }
        $slots = [];
        foreach ($base->slots as $position => $slot) {
            $name = $table->columns[$position] ?? null;
            $slots[] = $name === null ? $slot : new OutputSlot($name, $slot->type, $slot->nullability, null, $slot);
        }
        $shape = new RowShape($slots, $base->missing);
        if ($table->search !== null) {
            $this->member($shape, $table->search->columns, QueryMisuseRule::SearchColumnMissing, $derivation);
            $slots[] = new OutputSlot($table->search->sequence, new Known($table->search->order === SearchOrder::Depth ? new ArrayOf(Builtin::Record) : Builtin::Record), Nullability::NotNull);
        }
        if ($table->cycle !== null) {
            $this->member($shape, $table->cycle->columns, QueryMisuseRule::CycleColumnMissing, $derivation);
            $slots[] = new OutputSlot($table->cycle->mark, $this->mark($table, $derivation), Nullability::NotNull);
            $slots[] = new OutputSlot($table->cycle->path, new Known(new ArrayOf(Builtin::Record)), Nullability::NotNull);
        }

        return new RowShape($slots, $base->missing);
    }

    /**
     * Derives the mark values of a CYCLE clause and answers the type of the mark column.
     */
    public function mark(CommonTableExpression $table, Derivation $derivation): \SqlSemantics\Statement\Type\TypeFact
    {
        $cycle = $table->cycle;
        if ($cycle === null || $cycle->markValue === null || $cycle->markDefault === null) {
            return new Known(Builtin::Bool);
        }
        $empty = new Environment($derivation->context);
        $types = [$derivation->scalar($cycle->markValue, $empty)->type, $derivation->scalar($cycle->markDefault, $empty)->type];
        $type = (new Unification())->resolve($derivation->context, $types, 'CYCLE');
        if ($type instanceof Invalid) {
            $derivation->report($type->cause);
        }

        return $type;
    }

    /**
     * Reports the names of a SEARCH or CYCLE clause that are not columns of the table, when its columns are known.
     *
     * @param list<Name> $columns
     */
    public function member(RowShape $shape, array $columns, QueryMisuseRule $rule, Derivation $derivation): void
    {
        if (!$shape->complete()) {
            return;
        }
        foreach ($columns as $column) {
            $found = false;
            foreach ($shape->slots as $slot) {
                $found = $found || ($slot->name !== null && $derivation->context->columnNames->equal($slot->name->value, $column->value));
            }
            if (!$found) {
                $derivation->report(new QueryMisuse($rule, $column));
            }
        }
    }
}
