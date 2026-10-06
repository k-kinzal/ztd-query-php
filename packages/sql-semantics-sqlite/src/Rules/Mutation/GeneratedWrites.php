<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Mutation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnFacts;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnResolver;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\Joining;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Problem\GeneratedColumnWrite;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Problem\WriteKind;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\RowAssignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Upsert;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Reports the writes of generated columns, and counts the columns an INSERT without a column list fills.
 *
 * Rule: SQLITE-GENERATED-WRITE-001. A generated column, VIRTUAL or STORED,
 * cannot be written. Naming one in the column list of an INSERT is
 * rejected with "cannot INSERT into generated column" (`sqlite3Insert()` in
 * insert.c); assigning one in UPDATE SET or in DO UPDATE SET is rejected
 * with "cannot UPDATE generated column" (`sqlite3Update()` in update.c).
 * Both messages name the column as declared. An INSERT without a column
 * list supplies one value per column that is not generated.
 *
 * SQLite compiles the DO UPDATE of an upsert, and so checks it, only where
 * a uniqueness check of the INSERT selects that clause
 * (`sqlite3GenerateConstraintChecks()` in insert.c, `sqlite3UpsertOfIndex()`
 * in upsert.c). The rowid check runs when the row supplies the rowid: the
 * column list names it or the integer primary key, or there is no column
 * list and the table has an integer primary key. It selects the first
 * clause without a conflict target or with the rowid as its target. Every
 * unique index is checked on INSERT and selects the first clause whose
 * target matches it, or else the clause without a target; a conflict
 * target that matches no index is an error of its own. So a DO UPDATE is
 * certainly compiled when it has a target other than the rowid and no
 * earlier clause has one (a later clause for the same index never fires),
 * when the rowid check selects it, and when it is the only clause, has no
 * target and writes a WITHOUT ROWID table, whose primary key is a unique
 * index. Otherwise it depends on the unique indexes of the table, which a
 * declaration does not hold, and no write is reported.
 * Source: https://sqlite.org/gencol.html, https://sqlite.org/lang_insert.html,
 * https://sqlite.org/lang_upsert.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class GeneratedWrites
{
    /**
     * Answers how many columns of a complete row shape an INSERT without a column list fills: those that are not generated.
     */
    public function writable(RowShape $shape): int
    {
        $count = 0;
        foreach ($shape->slots as $slot) {
            $count += $slot->declaration()?->generated === true ? 0 : 1;
        }

        return $count;
    }

    /**
     * Reports each name of an INSERT column list that denotes a generated column of the written table.
     *
     * @param list<Name> $columns
     */
    public function inserted(array $columns, VisibleRelation $target, Derivation $derivation): void
    {
        foreach ($columns as $name) {
            $column = $this->written($name, $target, $derivation);
            if ($column !== null) {
                $derivation->report(new GeneratedColumnWrite(WriteKind::Insert, $column->name));
            }
        }
    }

    /**
     * Reports each column of UPDATE SET or DO UPDATE SET that is a generated column of the written table.
     *
     * @param list<Assignment|RowAssignment> $assignments
     */
    public function assigned(array $assignments, VisibleRelation $target, Derivation $derivation): void
    {
        foreach ($assignments as $assignment) {
            foreach ($assignment instanceof Assignment ? [$assignment->column] : $assignment->columns as $name) {
                $column = $this->written($name, $target, $derivation);
                if ($column !== null) {
                    $derivation->report(new GeneratedColumnWrite(WriteKind::Update, $column->name));
                }
            }
        }
    }

    /**
     * Answers the generated column a written name denotes, or null when it denotes another column or none.
     */
    public function written(Name $name, VisibleRelation $target, Derivation $derivation): ?Column
    {
        $found = (new Joining())->locate([$target], $name->value, $derivation);
        $column = $found === null ? null : $target->shape->slots[$found[1]]->declaration();

        return $column?->generated === true ? $column : null;
    }

    /**
     * Tells whether SQLite certainly compiles the DO UPDATE of the clause at a position.
     *
     * @param list<Upsert> $upserts The ON CONFLICT clauses in written order
     * @param int $index The position of the clause
     * @param Table $table The declaration of the written table
     * @param bool $supplied Whether the inserted row supplies the rowid
     * @param Environment $environment The scope of the conflict targets
     */
    public function reached(array $upserts, int $index, Table $table, bool $supplied, Environment $environment): bool
    {
        $rowid = $table->implicit === [] ? null : $table->implicit[0]->column;
        $first = null;
        $keyed = [];
        foreach ($upserts as $position => $upsert) {
            $byRowid = $upsert->target === null || ($rowid !== null && $this->rowidTarget($upsert->target, $rowid, $environment));
            $first ??= $byRowid ? $position : null;
            $keyed[$position] = !$byRowid;
        }
        if ($keyed[$index]) {
            return !in_array(true, array_slice($keyed, 0, $index), true);
        }
        if ($rowid !== null) {
            return $supplied && $first === $index;
        }

        return count($upserts) === 1 && $table->kind === RelationKind::BaseTable && $table->complete;
    }

    /**
     * Tells whether a conflict target is the rowid: a single term that is a name denoting it, possibly in parentheses.
     *
     * @param Column $rowid The column the rowid names denote
     */
    public function rowidTarget(ConflictTarget $target, Column $rowid, Environment $environment): bool
    {
        $resolution = count($target->terms) === 1 ? (new ColumnFacts())->denoted($target->terms[0]->expression, $environment) : null;

        return $resolution instanceof ResolvedColumn && $resolution->slot->declaration() === $rowid;
    }

    /**
     * Tells whether the inserted row supplies the rowid: through the column list, or by the integer primary key when there is no list.
     *
     * @param list<Name> $columns The column list; empty when none is written
     */
    public function supplied(array $columns, Table $table, VisibleRelation $target, Derivation $derivation): bool
    {
        if ($table->implicit === []) {
            return false;
        }
        $rowid = $table->implicit[0]->column;
        if ($columns === []) {
            return in_array($rowid, $table->columns, true);
        }
        $environment = new Environment($derivation->context, null, [$target]);
        foreach ($columns as $name) {
            $resolution = (new ColumnResolver())->find($environment, $name);
            if ($resolution instanceof ResolvedColumn && $resolution->slot->declaration() === $rowid) {
                return true;
            }
        }

        return false;
    }
}
