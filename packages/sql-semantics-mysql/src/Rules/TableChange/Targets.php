<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableChange;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Statement\Alter\AbsentTable;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;

/**
 * Resolves the existing table a DROP, RENAME, TRUNCATE or ALTER TABLE names, and answers its facts.
 *
 * Rule: MYSQL-CHANGE-TARGET-001. The name resolves by CORE-TABLE-LOOKUP-001
 * in the current database or the database written; common table expressions
 * are not visible to these statements. A declared table contributes one slot
 * per declared column, in order, each referring to the declaration, and an
 * open shape when its column list is incomplete; an undeclared or
 * conditionally resolved name contributes an open shape that names the
 * missing declaration; a missing or conflicting name contributes an empty
 * complete shape and is a diagnostic, except that a missing name under IF
 * EXISTS resolves to AbsentTable (the server only adds a note). Two names
 * denote the same table when their databases (the current one when none is
 * written) and their names are equal under the context's relation name
 * comparison. Precision: every slot of a declared table is a known type.
 * Terminates: one pass over the columns.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/identifier-qualifiers.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Targets
{
    /**
     * Resolves a table name and answers its facts.
     */
    public function target(Derivation $derivation, QualifiedName $name): RelationFact
    {
        $resolution = $derivation->table($name, $derivation->environment());
        if ($resolution instanceof DeclaredTable) {
            $slots = [];
            foreach ($resolution->table->columns as $column) {
                $slots[] = new OutputSlot($column->name, new Known($column->type), $column->nullability, $column);
            }

            return new RelationFact(new RowShape($slots, $resolution->table->complete ? [] : [new IncompleteMembers($resolution->table)]), $resolution);
        }
        if ($resolution instanceof UndeclaredTable || $resolution instanceof ConditionalTable) {
            return new RelationFact(new RowShape([], [$resolution->missing]), $resolution);
        }

        return new RelationFact(new RowShape([]), $resolution);
    }

    /**
     * Resolves a table name that IF EXISTS allows to be absent.
     */
    public function optional(Derivation $derivation, QualifiedName $name, bool $ifExists): RelationFact
    {
        $fact = $this->target($derivation, $name);

        return $ifExists && $fact->table instanceof MissingTable ? new RelationFact(new RowShape([]), new AbsentTable($name)) : $fact;
    }

    /**
     * Tells whether two written table names denote the same table.
     */
    public function same(AnalysisContext $context, QualifiedName $left, QualifiedName $right): bool
    {
        return $context->relationNames->equal(($left->schema ?? $context->searchPath[0])->value, ($right->schema ?? $context->searchPath[0])->value)
            && $context->relationNames->equal($left->name->value, $right->name->value);
    }
}
