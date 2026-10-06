<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\ImplicitSlot;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;

/**
 * Derives the row shape of a named table occurrence.
 *
 * Rule: MYSQL-TABLE-SHAPES-001. An unqualified name that a common table
 * expression in scope defines denotes it, the innermost first; any other
 * name resolves by CORE-TABLE-LOOKUP-001 in the current database or the
 * database written. A declared table contributes one slot per declared
 * column, in order, each referring to the declaration; a common table
 * contributes the slots of its definition; an undeclared or conditionally
 * resolved name contributes an open shape that names the missing
 * declaration; a missing or conflicting name contributes an empty complete
 * shape and the diagnostic. The INVISIBLE columns of a declared table are
 * no slots of the shape, so `*` and the TABLE statement do not select them,
 * but a name finds them on the occurrence. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/invisible-columns.html ("not part
 * of SELECT *", "can be referenced explicitly"),
 * https://dev.mysql.com/doc/refman/8.4/en/with.html ("a CTE name ...
 * takes precedence over a table of the same name"),
 * https://dev.mysql.com/doc/refman/8.4/en/join.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TableShapes
{
    /**
     * Resolves a table name and answers the facts of its occurrence.
     */
    public function named(QualifiedName $name, Derivation $derivation, Environment $environment): RelationFact
    {
        $resolution = $derivation->table($name, $environment);
        if ($resolution instanceof DeclaredTable) {
            $slots = [];
            foreach ($resolution->table->columns as $column) {
                $slots[] = new OutputSlot($column->name, new Known($column->type), $column->nullability, $column);
            }

            return new RelationFact(new RowShape($slots), $resolution);
        }
        if ($resolution instanceof CommonTable) {
            $binding = $environment->commonTable($name->name);

            return new RelationFact($binding === null ? new RowShape([]) : $binding->shape, $resolution);
        }
        if ($resolution instanceof UndeclaredTable || $resolution instanceof ConditionalTable) {
            return new RelationFact(new RowShape([], [$resolution->missing]), $resolution);
        }

        return new RelationFact(new RowShape([]), $resolution);
    }

    /**
     * Answers the columns of a declared table that a name finds although `*` does not select them: the INVISIBLE columns.
     *
     * @return list<ImplicitSlot>
     */
    public function implicit(RelationFact $fact): array
    {
        $slots = [];
        foreach ($fact->table instanceof DeclaredTable ? $fact->table->table->implicit : [] as $column) {
            $slots[] = new ImplicitSlot($column->names, new OutputSlot($column->column->name, new Known($column->column->type), $column->column->nullability, $column->column));
        }

        return $slots;
    }

    /**
     * Answers the output of the TABLE statement: every column of its table, in order.
     */
    public function explicit(ExplicitTable $table, RelationFact $fact, Derivation $derivation): QueryFact
    {
        $fields = [];
        foreach ($fact->shape->slots as $position => $slot) {
            $fields[] = new Field($position, new OutputSlot($slot->name, $slot->type, $slot->nullability, null, $slot, $slot->unnamed), null, new ResolvedColumn($table, $slot));
        }
        if (!$fact->shape->complete()) {
            $fields[] = new OpenStar($fact->shape->missing);
        }

        return new QueryFact($fields, $derivation->context->columnNames);
    }
}
