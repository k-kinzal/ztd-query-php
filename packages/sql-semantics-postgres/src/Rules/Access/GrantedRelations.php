<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Access;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;

/**
 * Resolves a relation named by GRANT or REVOKE and checks the column privileges on it.
 *
 * Rule: PG-GRANT-RELATION-001. The name is resolved along the schema search
 * path (CORE-TABLE-LOOKUP-001); a grant sees no common table. A declared
 * relation contributes one slot per declared column and is open when its
 * column list is incomplete; an undeclared or conditionally resolved name
 * contributes an open shape naming the missing declaration; conflicting
 * declarations are a diagnostic. GRANT ... ON TABLE also accepts a
 * sequence, and a context declares no sequences, so a name that no
 * declared relation has is not certainly wrong: it is recorded as an
 * undeclared relation, never as a missing table. A column of a column
 * privilege that a declared, complete relation certainly lacks is reported.
 * Termination: one lookup, then one pass over finite lists.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html, https://www.postgresql.org/docs/17/ddl-priv.html,
 * https://www.postgresql.org/docs/release/8.2.0/ (sequences in GRANT ON TABLE). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class GrantedRelations
{
    /**
     * Resolves the relation, records its facts on the reference and reports the columns it certainly lacks.
     *
     * @param list<Privilege> $privileges The privileges of the statement
     */
    public function derive(Derivation $derivation, RelationReference $relation, array $privileges): RelationFact
    {
        $resolution = $derivation->table($relation->name, $derivation->environment());
        if ($resolution instanceof MissingTable) {
            $resolution = new UndeclaredTable(new UndeclaredRelation($relation->name));
        }
        $shape = new RowShape([]);
        if ($resolution instanceof DeclaredTable) {
            $slots = [];
            foreach ($resolution->table->columns as $column) {
                $slots[] = new OutputSlot($column->name, new Known($column->type), $column->nullability, $column);
            }
            $shape = new RowShape($slots, $resolution->table->complete ? [] : [new IncompleteMembers($resolution->table)]);
            $this->columns($derivation, $relation, $resolution, $privileges);
        } elseif ($resolution instanceof UndeclaredTable || $resolution instanceof ConditionalTable) {
            $shape = new RowShape([], [$resolution->missing]);
        }

        return $derivation->target($relation, new RelationFact($shape, $resolution));
    }

    /**
     * Reports the columns of column privileges that a declared, complete relation lacks.
     *
     * @param list<Privilege> $privileges
     */
    public function columns(Derivation $derivation, RelationReference $relation, DeclaredTable $resolution, array $privileges): void
    {
        if (!$resolution->table->complete) {
            return;
        }
        foreach ($privileges as $privilege) {
            foreach ($privilege->columns as $column) {
                if ($resolution->table->matchingColumns($column->value, $derivation->context->columnNames) === []) {
                    $derivation->report(new MissingColumn($column, $relation->name));
                }
            }
        }
    }
}
