<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;

/**
 * Derives the parts that the data manipulation statements share: written columns, values and assignments.
 *
 * Rule: MYSQL-DML-SCOPE-001. A written column is a column use resolved
 * among the tables the statement writes; it becomes a field with the type
 * and nullability of the column. A value is derived where the statement
 * says; the value DEFAULT is derived where the only field of the
 * environment is the column it is assigned to (MYSQL-DML-DEFAULT-001).
 * Where the server requires distinct written columns (INSERT column lists
 * and INSERT ... SET), a column resolved twice to the same column, or named
 * twice when it does not resolve, is reported. Terminates: one pass over
 * the finite lists. Source: https://dev.mysql.com/doc/refman/8.4/en/insert.html,
 * https://dev.mysql.com/doc/refman/8.4/en/update.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class WriteScope
{
    /**
     * Turns the fact of a written column into the field the column stands for.
     */
    public function field(int $position, ColumnUse $column, ScalarFact $fact): Field
    {
        $origin = $fact->resolution instanceof ResolvedColumn ? $fact->resolution->slot : null;

        return new Field($position, new OutputSlot($column->name, $fact->type, $fact->nullability, null, $origin), null, $fact->resolution);
    }

    /**
     * Derives a value assigned to a column.
     */
    public function value(Scalar $value, Field $column, Derivation $derivation, Environment $environment): ScalarFact
    {
        if ($value instanceof DefaultRequest) {
            return $derivation->scalar($value, new Environment($derivation->context, $environment, [], [], [$column]));
        }

        return (new Operands())->single($derivation->scalar($value, $environment), $derivation);
    }

    /**
     * Derives assignments: each column where the written tables are visible, each value in its environment.
     *
     * @param list<Assignment> $assignments
     * @param bool $distinct Whether the server requires distinct columns
     * @return list<Field> The assigned columns in written order
     */
    public function assign(array $assignments, Derivation $derivation, Environment $columns, Environment $values, bool $distinct): array
    {
        $fields = [];
        foreach ($assignments as $position => $assignment) {
            $field = $this->field($position, $assignment->column, $derivation->scalar($assignment->column, $columns));
            $this->value($assignment->value, $field, $derivation, $values);
            $fields[] = $field;
        }
        if ($distinct) {
            $this->distinct($fields, $derivation);
        }

        return $fields;
    }

    /**
     * Reports each column written again.
     *
     * @param list<Field> $fields
     */
    public function distinct(array $fields, Derivation $derivation): void
    {
        $seen = [];
        foreach ($fields as $field) {
            $resolution = $field->resolution;
            $key = $resolution instanceof ResolvedColumn ? 'slot:' . spl_object_id($resolution->slot) : 'name:' . $derivation->context->columnNames->fold($field->name->value ?? '');
            if (isset($seen[$key]) && $field->name !== null) {
                $derivation->report(new DuplicateColumn($field->name));
            }
            $seen[$key] = true;
        }
    }
}
