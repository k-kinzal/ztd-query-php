<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Catalog;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\DeclaredTypes;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Publication\PublicationTable;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Derives the facts of a table published by CREATE or ALTER PUBLICATION.
 *
 * Rule: PG-PUBLICATION-TABLE-001. The table name is resolved along the
 * schema search path (CORE-TABLE-LOOKUP-001); a publication sees no common
 * table. A declared table contributes one slot per declared column and is
 * open when its column list is incomplete; an undeclared or conditionally
 * resolved name contributes an open shape naming the missing declaration; a
 * missing or conflicting name is a diagnostic. The column list names
 * columns of the table: against a declared table, a column it does not have
 * (when its columns are complete), a system column and a repeated column are
 * diagnostics. The row filter is evaluated with the table visible under its
 * name, as the server adds it to the filter's namespace.
 * Termination: one pass over the finite column list.
 * Source: https://www.postgresql.org/docs/17/sql-createpublication.html,
 * https://www.postgresql.org/docs/17/logical-replication-row-filter.html,
 * https://www.postgresql.org/docs/17/logical-replication-col-lists.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class PublishedTables
{
    /**
     * Resolves the table, checks the column list, derives the row filter and answers the facts of the occurrence.
     */
    public function derive(PublicationTable $table, Derivation $derivation, Environment $environment): RelationFact
    {
        $resolution = $derivation->table($table->table->name, new Environment($environment->context));
        $shape = new RowShape([]);
        if ($resolution instanceof DeclaredTable) {
            $slots = [];
            foreach ($resolution->table->columns as $column) {
                $slots[] = new OutputSlot($column->name, (new DeclaredTypes())->fact($column->type), $column->nullability, $column);
            }
            $shape = new RowShape($slots, $resolution->table->complete ? [] : [new IncompleteMembers($resolution->table)]);
            $this->columns($table, $resolution, $derivation);
        } elseif ($resolution instanceof UndeclaredTable || $resolution instanceof ConditionalTable) {
            $shape = new RowShape([], [$resolution->missing]);
        }
        if ($table->where !== null) {
            $derivation->scalar($table->where, new Environment($environment->context, null, [new VisibleRelation($table, $shape, null, $table->table->name)]));
        }

        return new RelationFact($shape, $resolution);
    }

    /**
     * Reports the names of the column list a declared table does not offer, and repeated names.
     */
    public function columns(PublicationTable $table, DeclaredTable $resolution, Derivation $derivation): void
    {
        $seen = [];
        $comparison = $derivation->context->columnNames;
        foreach ($table->columns as $column) {
            $matches = $resolution->table->matchingColumns($column->value, $comparison);
            if ($matches !== [] && !in_array($matches[0], $resolution->table->columns, true)) {
                $derivation->report(new CatalogMisuse(CatalogMisuseRule::PublicationSystemColumn, [$column->value]));
            } elseif ($matches === [] && $resolution->table->complete) {
                $derivation->report(new CatalogMisuse(CatalogMisuseRule::PublicationMissingColumn, [$column->value, $resolution->table->name->name->value]));
            }
            foreach ($seen as $earlier) {
                if ($comparison->equal($earlier, $column->value)) {
                    $derivation->report(new CatalogMisuse(CatalogMisuseRule::PublicationDuplicateColumn, [$column->value]));
                }
            }
            $seen[] = $column->value;
        }
    }
}
