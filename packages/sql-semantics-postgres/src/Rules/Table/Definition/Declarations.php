<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\SystemColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\Generated;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\Identity;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\NotNull;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TablePrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeClause;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeOptionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\PartitionOf;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\TableForm;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Derives the declaration a table definition provides.
 *
 * Rule: PG-TABLE-DECLARATION-001. The columns of a table defined by a column
 * list are, in order: the columns of the INHERITS parents (a column of the
 * same name in several parents, or in the list, is merged into one), then
 * each column definition and the columns each LIKE clause copies at its
 * position. A partition has the columns of its parent; a typed table the
 * attributes of its type. Each column has the type of PG-COLUMN-TYPE-001. A
 * column is NOT NULL when it is declared NOT NULL, is a serial or identity
 * column, belongs to a PRIMARY KEY, or is copied from a NOT NULL column of a
 * parent, a LIKE source or the partitioned table ("Not-null constraints are
 * always copied to the new table"); every other column can be NULL. A
 * column of a type the context cannot identify is declared with the type its
 * name denotes on the search path. The declaration is complete only up to
 * the first column whose type name is an error or whose parent, LIKE source
 * or composite type the context does not declare. A column is generated
 * when its definition has a generation clause, when it is inherited from a
 * generated column of a parent or of the partitioned table, or when a LIKE
 * clause copies a generated column and its options include GENERATED
 * (INCLUDING GENERATED or INCLUDING ALL, applied in order with the EXCLUDING
 * options; without it the copy is a regular column). An identity column is
 * not generated in this sense. Source of the inheritance rule: MergeAttributes,
 * tablecmds.c; https://www.postgresql.org/docs/17/ddl-generated-columns.html. The system
 * columns are implicit (PG-SYSTEM-COLUMNS-001). A foreign table is declared as one, every other
 * definition as a base table (PG-RELATION-KIND-001).
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html, https://www.postgresql.org/docs/17/ddl-inherit.html.
 * Termination: one pass over the parents and the elements. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Declarations
{
    /**
     * Builds the declaration of a table definition under its name.
     */
    public function table(TableForm $form, Derivation $derivation, QualifiedName $name, RelationKind $kind = RelationKind::BaseTable): Table
    {
        $set = new ColumnSet();
        if ($form instanceof ListedColumns) {
            $this->listed($form, $derivation, $set);
        } elseif ($form instanceof PartitionOf) {
            $this->copy($form->parent->name, $derivation, $set, null);
        } else {
            $set->close();
        }
        foreach ($form->elements() as $element) {
            $this->constrain($element, $set);
        }

        return new Table($name, $derivation->context->profile, $set->columns(), (new SystemColumns())->implicit(), !$set->closed(), $kind);
    }

    /**
     * Adds the inherited columns, the column definitions and the LIKE columns of a column list.
     */
    public function listed(ListedColumns $form, Derivation $derivation, ColumnSet $set): void
    {
        foreach ($form->parents as $parent) {
            $this->copy($parent->name, $derivation, $set, null);
        }
        $types = new ColumnTyping();
        foreach ($form->elements as $element) {
            if ($element instanceof LikeClause) {
                $this->copy($element->table, $derivation, $set, $element);
            } elseif ($element instanceof ColumnDefinition) {
                $type = $types->descriptor($element->type, $derivation->context);
                if ($type === null) {
                    $set->close();
                }
                $notNull = $types->serial($element->type) !== null || $this->notNull($element->qualifiers);
                if ($type !== null && !$set->add($element->name, $type, $notNull, $this->generated($element->qualifiers)) && $notNull) {
                    $set->require($element->name);
                }
            }
        }
    }

    /**
     * Copies the columns of a parent or LIKE source, or closes the set when the context does not declare its columns.
     *
     * @param LikeClause|null $like The LIKE clause that copies the columns, or null for a parent
     */
    public function copy(QualifiedName $source, Derivation $derivation, ColumnSet $set, ?LikeClause $like): void
    {
        $resolution = $derivation->table($source, $derivation->environment());
        if (!$resolution instanceof DeclaredTable || !$resolution->table->complete) {
            $set->close();

            return;
        }
        $generation = $like !== null && $this->includesGenerated($like);
        foreach ($resolution->table->columns as $column) {
            if ($like === null) {
                $set->inherit($column);
            } else {
                $set->add($column->name, $column->type, $column->nullability === \SqlSemantics\Statement\Type\Nullability::NotNull, $generation && $column->generated);
            }
        }
    }

    /**
     * Tells whether the options of a LIKE clause, applied in order, include the generation expressions.
     */
    public function includesGenerated(LikeClause $like): bool
    {
        $included = false;
        foreach ($like->options as $option) {
            if ($option->kind === LikeOptionKind::Generated || $option->kind === LikeOptionKind::All) {
                $included = $option->including;
            }
        }

        return $included;
    }

    /**
     * Tells whether column qualifiers hold a generation clause.
     *
     * @param list<Clause> $qualifiers
     */
    public function generated(array $qualifiers): bool
    {
        foreach ($qualifiers as $qualifier) {
            if ($qualifier instanceof Generated) {
                return true;
            }
        }

        return false;
    }

    /**
     * Applies the NOT NULL facts of column options and primary keys.
     */
    public function constrain(Clause $element, ColumnSet $set): void
    {
        if ($element instanceof ColumnOptions && $this->notNull($element->qualifiers)) {
            $set->require($element->name);
        }
        if ($element instanceof TablePrimaryKey) {
            foreach ($element->columns as $column) {
                $set->require($column);
            }
        }
    }

    /**
     * Tells whether column qualifiers make the column NOT NULL: NOT NULL, PRIMARY KEY or an identity.
     *
     * @param list<Clause> $qualifiers
     */
    public function notNull(array $qualifiers): bool
    {
        foreach ($qualifiers as $qualifier) {
            if ($qualifier instanceof NotNull || $qualifier instanceof ColumnPrimaryKey || $qualifier instanceof Identity) {
                return true;
            }
        }

        return false;
    }
}
