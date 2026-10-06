<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryRoots;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyDirection;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyStream;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTable;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Modification;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Derives the facts of COPY.
 *
 * Rule: PG-COPY-001. The table follows PG-TARGET-TABLE-001; COPY TO reads
 * only a table and COPY FROM writes neither a materialized view nor a
 * sequence (PG-RELATION-KIND-001; `cannot copy from view "v"`). A column of
 * the column list must be a column of the table and may be listed once;
 * without a list every column is copied. The WHERE condition sees the
 * table and is allowed with COPY FROM only. The query of COPY TO is derived
 * as a nested query; a data-modifying statement without RETURNING and a
 * SELECT INTO are reported. PROGRAM with STDIN or STDOUT is reported. The
 * options follow PG-COPY-OPTIONS-001. COPY returns no rows: the data goes
 * to or comes from the file, the program or the client.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CopyFacts
{
    /**
     * Derives COPY of a table.
     */
    public function table(CopyTable $copy, Derivation $derivation): void
    {
        $environment = $derivation->environment();
        $fact = $derivation->relation($copy->table, $environment);
        $table = (new Targets())->visible($copy->table, $fact);
        $this->kind($copy, (new RelationKinds())->of($fact), $derivation);
        $copied = $this->columns($copy->columns, $table, $derivation);
        if ($copy->where !== null) {
            if ($copy->direction === CopyDirection::To) {
                $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::CopyWhereWithTo));
            }
            $derivation->scalar($copy->where, new Environment($derivation->context, $environment, [$table]));
        }
        $this->program($copy->program, $copy->file, $derivation);
        (new CopyOptions())->check($copy->binary, $copy->delimiters !== null, $copy->legacy, $copy->options, $copied, $derivation);
        $placement = new Placement();
        $placement->values($copy, [], [], $derivation);
        $placement->into($copy, $derivation);
    }

    /**
     * Reports a relation of a kind COPY cannot read from or write into.
     *
     * COPY TO reads only tables: a view, a materialized view, a foreign table
     * or a sequence is refused. COPY FROM refuses a materialized view and a
     * sequence; a view needs an INSTEAD OF INSERT trigger and a foreign table
     * a wrapper that inserts, which the context does not tell.
     */
    public function kind(CopyTable $copy, ?RelationKind $kind, Derivation $derivation): void
    {
        $rule = $copy->direction === CopyDirection::To ? match ($kind) {
            RelationKind::View => KindRule::CopyFromView,
            RelationKind::MaterializedView => KindRule::CopyFromMaterializedView,
            RelationKind::ForeignTable => KindRule::CopyFromForeignTable,
            RelationKind::Sequence => KindRule::CopyFromSequence,
            RelationKind::BaseTable, null => null,
        } : match ($kind) {
            RelationKind::MaterializedView => KindRule::CopyToMaterializedView,
            RelationKind::Sequence => KindRule::CopyToSequence,
            RelationKind::BaseTable, RelationKind::View, RelationKind::ForeignTable, null => null,
        };
        if ($rule !== null) {
            $derivation->report(new KindProblem($rule, $copy->table->table->name->name));
        }
    }

    /**
     * Derives COPY of a query.
     */
    public function query(CopyQuery $copy, Derivation $derivation): void
    {
        $fact = $derivation->query($copy->query, $derivation->environment());
        if ($copy->query instanceof Modification && !$copy->query->returnsRows()) {
            $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::CopyWithoutReturning));
        }
        $first = $copy->query instanceof Modification ? null : (new QueryRoots())->first($copy->query);
        if ($first?->into !== null) {
            $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::CopySelectInto));
        }
        $this->program($copy->program, $copy->file, $derivation);
        $copied = [];
        foreach ($fact->fields() ?? [] as $field) {
            if ($field->name !== null) {
                $copied[] = $field->name;
            }
        }
        (new CopyOptions())->check(false, false, $copy->legacy, $copy->options, $fact->fields() === null ? null : $copied, $derivation);
        $placement = new Placement();
        $placement->values($copy, [], [], $derivation);
        $placement->into($copy, $derivation, $first);
        $placement->modifying($copy, $copy->query, $derivation);
    }

    /**
     * Derives the column list and answers the columns copied, or null when they are not known.
     *
     * @param list<Name> $columns
     *
     * @return list<Name>|null
     */
    public function columns(array $columns, VisibleRelation $table, Derivation $derivation): ?array
    {
        $names = $derivation->context->columnNames;
        $known = $table->shape->complete() && $table->shape->slots !== [];
        $seen = [];
        foreach ($columns as $column) {
            $found = false;
            foreach ($table->shape->slots as $slot) {
                $found = $found || ($slot->name !== null && $names->equal($slot->name->value, $column->value));
            }
            if (!$found && $known) {
                $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::UnknownTargetColumn, $column->value, $table->relation instanceof TargetTable ? $table->relation->table->name->name->value : ''));
            }
            $key = $names->fold($column->value);
            if (isset($seen[$key])) {
                $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::RepeatedInsertColumn, $column->value));
            }
            $seen[$key] = true;
        }
        if ($columns !== []) {
            return $columns;
        }
        if (!$table->shape->complete()) {
            return null;
        }
        $all = [];
        foreach ($table->shape->slots as $slot) {
            if ($slot->name !== null) {
                $all[] = $slot->name;
            }
        }

        return $all;
    }

    /**
     * Reports PROGRAM written with STDIN or STDOUT.
     */
    public function program(bool $program, StringConstant|CopyStream $file, Derivation $derivation): void
    {
        if ($program && $file instanceof CopyStream) {
            $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::CopyProgramWithClient));
        }
    }
}
