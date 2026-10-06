<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\From\FromScope;
use SqlSemantics\Platform\MySql\Rules\Query\Projection;
use SqlSemantics\Platform\MySql\Rules\Query\TailFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\UnknownDeleteTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;

/**
 * Derives the facts of UPDATE and DELETE.
 *
 * Rule: MYSQL-UPDATE-001 and MYSQL-DELETE-001. The common tables of WITH
 * are bound first and are visible in every query of the statement. The
 * table references are derived as a FROM clause is (MYSQL-FROM-001), each
 * later reference seeing the earlier ones for LATERAL. The columns of the
 * assignments, their values, WHERE and ORDER BY see every table of the
 * references; the value DEFAULT is the default of its column; LIMIT sees
 * none. An UPDATE whose references hold more than one table is a
 * multiple-table UPDATE, which takes neither ORDER BY nor LIMIT. A single-
 * table DELETE sees its table under its correlation name, or its table name
 * when it has none. Each table a multiple-table DELETE deletes from must
 * name a table of its references. A column an UPDATE assigns, and a table
 * a multiple-table DELETE deletes from, must belong to a table or view; a
 * derived table, a table function or a common table is not updatable
 * (ER_NON_UPDATABLE_TABLE); a generated column takes only DEFAULT
 * (MYSQL-GENERATED-WRITE-001). The statements return no rows.
 * Terminates: one pass over the finite parts. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/update.html,
 * https://dev.mysql.com/doc/refman/8.4/en/delete.html,
 * https://dev.mysql.com/doc/refman/8.4/en/with.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ChangeFacts
{
    /**
     * Derives UPDATE.
     */
    public function update(Update $update, Derivation $derivation, Environment $outer): void
    {
        $base = $this->base($update->with, $derivation, $outer);
        $visible = $this->references($update->tables, $derivation, $base);
        $environment = new Environment($derivation->context, $base, $visible);
        $fields = (new WriteScope())->assign($update->assignments, $derivation, $environment, $environment, false);
        (new GeneratedWrites())->assignments($update->assignments, $fields, $derivation);
        foreach ($fields as $field) {
            if ($field->resolution instanceof ResolvedColumn && !$this->updatable($field->resolution->relation, $derivation)) {
                $derivation->report(new WriteMisuse(WriteRule::NonUpdatableTarget));
            }
        }
        $this->clauses($update->where, $update->orderBy, $update->limit, $derivation, $base, $environment);
        if (count($visible) > 1 && $update->orderBy !== []) {
            $derivation->report(new WriteMisuse(WriteRule::OrderedMultipleUpdate));
        }
        if (count($visible) > 1 && $update->limit !== null) {
            $derivation->report(new WriteMisuse(WriteRule::LimitedMultipleUpdate));
        }
    }

    /**
     * Derives a single-table DELETE.
     */
    public function delete(Delete $delete, Derivation $derivation, Environment $outer): void
    {
        $base = $this->base($delete->with, $derivation, $outer);
        $fact = $derivation->relation($delete->table, $base);
        $environment = new Environment($derivation->context, $base, [new VisibleRelation($delete->table, $fact->shape, $delete->table->alias, $delete->table->name)]);
        $this->clauses($delete->where, $delete->orderBy, $delete->limit, $derivation, $base, $environment);
    }

    /**
     * Derives a multiple-table DELETE.
     */
    public function deleteMultiple(MultipleDelete $delete, Derivation $derivation, Environment $outer): void
    {
        $base = $this->base($delete->with, $derivation, $outer);
        $visible = $this->references($delete->tables, $derivation, $base);
        $projection = new Projection();
        foreach ($delete->targets as $target) {
            $found = null;
            foreach ($visible as $relation) {
                $found ??= $projection->admits($derivation, $relation, $target) ? $relation : null;
            }
            if ($found === null) {
                $derivation->report(new UnknownDeleteTable($target));
            } elseif (!$this->updatable($found->relation, $derivation)) {
                $derivation->report(new WriteMisuse(WriteRule::NonUpdatableTarget));
            }
        }
        $this->clauses($delete->where, [], null, $derivation, $base, new Environment($derivation->context, $base, $visible));
    }

    /**
     * Tells whether rows of a relation occurrence can be changed: a table or view, not a derived table, a table function or a common table.
     */
    public function updatable(Relation $relation, Derivation $derivation): bool
    {
        if ($relation instanceof DerivedTable || $relation instanceof JsonTable) {
            return false;
        }

        return !$derivation->facts()->relation($relation)->table instanceof CommonTable;
    }

    /**
     * Binds the common tables of WITH, when there is one.
     */
    public function base(?WithClause $with, Derivation $derivation, Environment $outer): Environment
    {
        return $with === null ? $outer : $with->bind($derivation, $outer);
    }

    /**
     * Derives the table references and answers the relations they make visible.
     *
     * @param list<Relation> $tables
     * @return list<VisibleRelation>
     */
    public function references(array $tables, Derivation $derivation, Environment $base): array
    {
        $visible = [];
        $from = new FromScope();
        foreach ($tables as $table) {
            array_push($visible, ...$from->open($table, $derivation, $base, $visible)->visible);
        }

        return $visible;
    }

    /**
     * Derives WHERE and ORDER BY where the tables are visible, and LIMIT where they are not.
     *
     * @param list<OrderItem> $orderBy
     */
    public function clauses(?Scalar $where, array $orderBy, ?Limit $limit, Derivation $derivation, Environment $base, Environment $environment): void
    {
        if ($where !== null) {
            $derivation->scalar($where, $environment);
        }
        foreach ($orderBy as $item) {
            $derivation->scalar($item->expression, $environment);
        }
        (new TailFacts())->limit($limit, $derivation, $base);
    }
}
