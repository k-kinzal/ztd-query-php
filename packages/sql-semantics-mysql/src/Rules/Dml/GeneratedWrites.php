<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\GeneratedColumnWrite;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;

/**
 * Reports the values a write gives to generated columns.
 *
 * Rule: MYSQL-GENERATED-WRITE-001. "The only value permitted for a
 * generated column in an INSERT, REPLACE or UPDATE statement is DEFAULT":
 * a written column whose declaration is generated (VIRTUAL or STORED) takes
 * only a default value item, the keyword DEFAULT or the function
 * DEFAULT(col) (parentheses do not make an item), else the statement is
 * rejected with ER_NON_DEFAULT_VALUE_FOR_GENERATED_COLUMN, naming the
 * declared column and table. This holds for each row of VALUES (with or
 * without a column list), INSERT ... SET, the assignments of ON DUPLICATE
 * KEY UPDATE and of a single- or multiple-table UPDATE; IGNORE and sql_mode
 * do not change it. A query source that is a VALUES statement (inside
 * parentheses, WITH, ORDER BY or LIMIT) is turned into the rows of INSERT
 * ... VALUES (sql/parse_tree_nodes.cc `PT_insert::make_cmd`), so each of
 * its values is checked as a value of a row. Another query source gives a
 * default value item only when it is one query block whose select item at
 * that position is DEFAULT(col); the field of a set operation, of TABLE or
 * of an expanded star is not one.
 * A column of a view, a derived table or a common table is not a declared
 * generated column here. LOAD DATA computes generated columns and ignores
 * the values it reads or assigns for them (`fill_record` skips them), so it
 * is not checked. Terminates: one pass over the finite lists.
 * Source: sql/sql_resolver.cc `validate_gc_assignment` (5.7.44, 8.0.44,
 * 8.4.7, 9.1.0), called from sql/sql_insert.cc
 * `Sql_cmd_insert_base::prepare_inner` and sql/sql_update.cc
 * `Sql_cmd_update::prepare_inner`;
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class GeneratedWrites
{
    /**
     * Reports a value other than a default value item written into a generated column of a declared table.
     *
     * @param Scalar|null $value The value, or null for a field no single expression computes
     * @param Name|null $table The declared name of the written table, or null when the table is not a declared table
     */
    public function value(Field $column, ?Scalar $value, ?Name $table, Derivation $derivation): void
    {
        $declaration = $column->column();
        if ($table !== null && $declaration !== null && $declaration->generated && !$this->default($value)) {
            $derivation->report(new GeneratedColumnWrite($declaration->name, $table));
        }
    }

    /**
     * Reports the assignments that write a value other than a default value item into a generated column.
     *
     * @param list<Assignment> $assignments
     * @param list<Field> $fields The assigned columns, one per assignment in written order
     */
    public function assignments(array $assignments, array $fields, Derivation $derivation): void
    {
        foreach ($fields as $position => $field) {
            $resolution = $field->resolution;
            if ($resolution instanceof ResolvedColumn) {
                $this->value($field, $assignments[$position]->value, $this->table($resolution->relation, $derivation), $derivation);
            }
        }
    }

    /**
     * Reports each generated written column that the rows of a query source fill with something else than a default value item.
     *
     * @param list<Field> $written The written columns
     * @param Name|null $table The declared name of the written table, or null when the table is not a declared table
     */
    public function query(array $written, Query $source, QueryFact $rows, ?Name $table, Derivation $derivation): void
    {
        $values = $this->valueRows($source);
        if ($values !== null) {
            foreach ($values->rows as $row) {
                foreach ($row->values as $position => $value) {
                    if (isset($written[$position])) {
                        $this->value($written[$position], $value, $table, $derivation);
                    }
                }
            }

            return;
        }
        foreach ((new TableDeclaration())->settled($rows) as $field) {
            if (isset($written[$field->position])) {
                $this->value($written[$field->position], $field->expression, $table, $derivation);
            }
        }
    }

    /**
     * Answers the VALUES statement a query source consists of, inside parentheses, WITH, ORDER BY, LIMIT or locking clauses; the server inserts its rows as the rows of INSERT ... VALUES.
     */
    public function valueRows(Query $source): ?ValuesQuery
    {
        while ($source instanceof QueryExpression || $source instanceof ParenthesizedQuery || $source instanceof QueryStatement) {
            $source = $source instanceof QueryExpression ? $source->body : $source->query;
        }

        return $source instanceof ValuesQuery ? $source : null;
    }

    /**
     * Tells whether a value is a default value item: DEFAULT or DEFAULT(col), inside any parentheses; no value is none.
     */
    public function default(?Scalar $value): bool
    {
        while ($value instanceof Grouped) {
            $value = $value->operand;
        }

        return $value instanceof DefaultRequest || $value instanceof DefaultOfColumn;
    }

    /**
     * Answers the declared name of the table or view a relation occurrence reads, or null when it reads no declared table.
     */
    public function table(Relation $relation, Derivation $derivation): ?Name
    {
        $table = $derivation->facts()->relation($relation)->table;

        return $table instanceof DeclaredTable ? $table->table->name->name : null;
    }
}
