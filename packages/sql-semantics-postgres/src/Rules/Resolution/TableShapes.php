<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Resolution;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\DeclaredTypes;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Modification;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\ImplicitSlot;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Derives the row shape of one use of a named relation.
 *
 * Rule: PG-TABLE-SHAPE-001. An unqualified name denotes the nearest common
 * table of that name, then a relation found along the schema search path
 * (CORE-TABLE-LOOKUP-001). A declared relation contributes one slot per
 * declared column, in order, each referring to the declaration, and is open
 * when its column list is incomplete; a common table contributes the slots
 * it is bound with; an undeclared or conditionally resolved name contributes
 * an open shape that names the missing declaration; a missing or conflicting
 * name contributes an empty shape and is a diagnostic. The column aliases
 * rename the slots (PG-COLUMN-ALIAS-001). The arguments of TABLESAMPLE see
 * the enclosing query only; sampling a common table is reported, and so is reading a common table
 * computed by a data-modifying statement without RETURNING. The
 * implicit columns of a declaration are found by name only.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM,
 * https://www.postgresql.org/docs/17/ddl-schemas.html#DDL-SCHEMAS-PATH. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class TableShapes
{
    /**
     * Resolves the name of a table input and derives the shape of the use.
     */
    public function input(TableInput $input, Derivation $derivation, Environment $environment): RelationFact
    {
        $resolution = $derivation->table($input->table->name, $environment);
        if ($input->sample !== null) {
            $input->sample->deriveClause($derivation, $environment);
            if ($resolution instanceof CommonTable) {
                $derivation->report(new QueryMisuse(QueryMisuseRule::TablesampleOnCommonTable));
            }
        }
        $shape = new RowShape([]);
        if ($resolution instanceof DeclaredTable) {
            $slots = [];
            foreach ($resolution->table->columns as $column) {
                $slots[] = new OutputSlot($column->name, (new DeclaredTypes())->fact($column->type), $column->nullability, $column);
            }
            $shape = new RowShape($slots, $resolution->table->complete ? [] : [new IncompleteMembers($resolution->table)]);
        } elseif ($resolution instanceof CommonTable) {
            $binding = $environment->commonTable($input->table->name->name);
            $definition = $binding?->definition;
            if ($definition instanceof CommonTableExpression && $definition->query instanceof Modification && !$definition->query->returnsRows()) {
                $derivation->report(new QueryMisuse(QueryMisuseRule::WithoutReturning, $definition->name));
            }
            $slots = [];
            foreach ($binding->shape->slots ?? [] as $slot) {
                $slots[] = new OutputSlot($slot->name, $slot->type, $slot->nullability, null, $slot);
            }
            $shape = new RowShape($slots, $binding->shape->missing ?? []);
        } elseif ($resolution instanceof UndeclaredTable || $resolution instanceof ConditionalTable) {
            $shape = new RowShape([], [$resolution->missing]);
        }

        return new RelationFact((new ColumnAliases())->apply($shape, $input->alias, $input->columns, $derivation), $resolution);
    }

    /**
     * Answers the implicit columns of the relation a use resolved to.
     *
     * @return list<ImplicitSlot>
     */
    public function implicit(RelationFact $fact): array
    {
        $implicit = [];
        foreach ($fact->table instanceof DeclaredTable ? $fact->table->table->implicit : [] as $column) {
            $implicit[] = new ImplicitSlot($column->names, new OutputSlot($column->column->name, (new DeclaredTypes())->fact($column->column->type), $column->column->nullability, $column->column));
        }

        return $implicit;
    }
}
