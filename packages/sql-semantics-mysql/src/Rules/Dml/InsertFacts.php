<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\RowAlias;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the facts of INSERT and REPLACE.
 *
 * Rule: MYSQL-INSERT-001. The written table is the one visible relation of
 * the columns, the rows and the assignments; a value may refer to a column
 * of the table (insert.html: a value may refer to a column set earlier in
 * the row). The written columns are the column list, or every column of the
 * table; they must be distinct. Each row of VALUES has one value per written
 * column, except an empty row without a column list, which writes the
 * defaults; rows also agree with each other, an empty row included. A query source is derived where
 * the table is not visible and must return one column per written column.
 * ON DUPLICATE KEY UPDATE assigns columns of the written table; its values
 * see the written table, the row alias (MYSQL-DML-ROW-ALIAS-001), whose
 * name may not be the table name, and, for a query source that is one query
 * block without GROUP BY, the relations of its FROM clause. Generated
 * columns take only DEFAULT (MYSQL-GENERATED-WRITE-001); a row or a query
 * whose count does not match is reported for the count only, as the
 * server stops there. A qualified star
 * in a 5.x column list is reported as the server's unknown column '*'.
 * Statements return no rows. Terminates: one pass over finite lists; the
 * source query is derived once. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/insert.html,
 * https://dev.mysql.com/doc/refman/8.4/en/insert-select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/insert-on-duplicate.html,
 * https://dev.mysql.com/doc/refman/8.4/en/replace.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class InsertFacts
{
    /**
     * Derives INSERT ... VALUES.
     */
    public function rows(InsertRows $insert, Derivation $derivation, Environment $outer): void
    {
        [$target, $written] = $this->open($insert->into, $derivation, $outer);
        $environment = new Environment($derivation->context, $outer, [$target]);
        $scope = new WriteScope();
        $generated = new GeneratedWrites();
        $table = $generated->table($insert->into->table, $derivation);
        $width = null;
        foreach ($insert->rows as $index => $row) {
            $count = count($row->values);
            $expected = $written === null ? $width : count($written);
            $mismatch = null;
            $columns = $count === 0 && $insert->into->columns === null ? null : $expected;
            $against = $columns !== null && $count !== $columns ? $columns : ($width !== null && $count !== $width ? $width : null);
            if ($against !== null) {
                $mismatch = new ValueCountMismatch($against, $count, $index + 1);
                $derivation->report($mismatch);
            }
            $width ??= $count;
            foreach ($row->values as $position => $value) {
                $column = $this->column($written, $position, $target, $mismatch);
                $scope->value($value, $column, $derivation, $environment);
                $generated->value($column, $value, $mismatch === null ? $table : null, $derivation);
            }
        }
        $this->duplicates($insert->onDuplicate, $insert->alias, $insert->into, $target, $written, [], $derivation, $outer);
    }

    /**
     * Derives INSERT ... SET.
     */
    public function set(InsertSet $insert, Derivation $derivation, Environment $outer): void
    {
        [$target] = $this->open($insert->into, $derivation, $outer);
        $environment = new Environment($derivation->context, $outer, [$target]);
        $written = (new WriteScope())->assign($insert->assignments, $derivation, $environment, $environment, true);
        (new GeneratedWrites())->assignments($insert->assignments, $written, $derivation);
        $this->duplicates($insert->onDuplicate, $insert->alias, $insert->into, $target, $written, [], $derivation, $outer);
    }

    /**
     * Derives INSERT ... SELECT, TABLE or VALUES.
     */
    public function query(InsertQuery $insert, Derivation $derivation, Environment $outer): void
    {
        [$target, $written] = $this->open($insert->into, $derivation, $outer);
        $rows = $derivation->query($insert->source, $outer);
        if ($written !== null && $rows->shape->complete() && count($rows->shape->slots) !== count($written)) {
            $derivation->report(new ValueCountMismatch(count($written), count($rows->shape->slots)));
        } elseif ($written !== null) {
            $generated = new GeneratedWrites();
            $generated->query($written, $insert->source, $rows, $generated->table($insert->into->table, $derivation), $derivation);
        }
        $sources = $insert->onDuplicate === [] ? [] : (new SourceRelations())->visible($insert->source, $derivation);
        $this->duplicates($insert->onDuplicate, null, $insert->into, $target, $written, $sources, $derivation, $outer);
    }

    /**
     * Derives the written table and the written columns.
     *
     * @return array{VisibleRelation, list<Field>|null} The table as a visible relation, and the written columns, or null when the table is not declared completely and no column list is written
     */
    public function open(InsertInto $into, Derivation $derivation, Environment $outer): array
    {
        $fact = $derivation->relation($into->table, $outer);
        $target = new VisibleRelation($into->table, $fact->shape, null, $into->table->name);
        if ($into->columns === null) {
            if (!$fact->shape->complete()) {
                return [$target, null];
            }
            $fields = [];
            foreach ($fact->shape->slots as $position => $slot) {
                $fields[] = new Field($position, new OutputSlot($slot->name, $slot->type, $slot->nullability, null, $slot));
            }

            return [$target, $fields];
        }
        $environment = new Environment($derivation->context, $outer, [$target]);
        $scope = new WriteScope();
        $fields = [];
        foreach ($into->columns->columns as $position => $column) {
            if ($column instanceof ColumnUse) {
                $fields[] = $scope->field($position, $column, $derivation->scalar($column, $environment));
                continue;
            }
            $problem = new WriteMisuse(WriteRule::WildcardColumn);
            $derivation->report($problem);
            $fields[] = new Field($position, new OutputSlot(null, new Invalid($problem), Nullability::Dependent));
        }
        $scope->distinct($fields, $derivation);

        return [$target, $fields];
    }

    /**
     * Answers the field a value at a position of a row is assigned to.
     *
     * @param list<Field>|null $written
     */
    public function column(?array $written, int $position, VisibleRelation $target, ?Diagnostic $mismatch): Field
    {
        if ($written !== null && isset($written[$position])) {
            return $written[$position];
        }
        if ($written === null) {
            return new Field($position, new OutputSlot(null, new Dependent($target->shape->missing), Nullability::Dependent));
        }
        Check::invariant($mismatch !== null, 'A value beyond the written columns is a reported count mismatch.');

        return new Field($position, new OutputSlot(null, new Invalid($mismatch), Nullability::Dependent));
    }

    /**
     * Derives the row alias and the assignments of ON DUPLICATE KEY UPDATE.
     *
     * @param list<Assignment> $assignments
     * @param list<Field>|null $written
     * @param list<VisibleRelation> $sources The relations of the FROM clause of a query source
     */
    public function duplicates(array $assignments, ?RowAlias $alias, InsertInto $into, VisibleRelation $target, ?array $written, array $sources, Derivation $derivation, Environment $outer): void
    {
        $visible = [$target];
        if ($alias !== null) {
            if ($derivation->context->relationNames->equal($alias->name->value, $into->table->name->name->value)) {
                $derivation->report(new Misuse(MisuseRule::DuplicateAlias));
            }
            $environment = $written === null ? new Environment($derivation->context, $outer, [$target]) : new Environment($derivation->context, $outer, [], [], $written);
            $visible[] = new VisibleRelation($alias, $derivation->relation($alias, $environment)->shape, $alias->name);
        }
        if ($assignments === []) {
            return;
        }
        $fields = (new WriteScope())->assign($assignments, $derivation, new Environment($derivation->context, $outer, [$target]), new Environment($derivation->context, $outer, [...$visible, ...$sources]), false);
        (new GeneratedWrites())->assignments($assignments, $fields, $derivation);
    }
}
