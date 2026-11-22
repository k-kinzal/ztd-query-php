<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Key;

use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnUnique;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ConstraintName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TablePrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableUnique;

/**
 * Collects every checked key occurrence in table declaration order.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TableKeyLowering
{
    /**
     * Resolves table key terms after collecting the inline clauses.
     *
     * @param list<TableColumn> $columns
     * @return list<PrimaryKey|UniqueKey>
     * @throws InvalidConstruction When a constraint has an unsupported role
     */
    public function lower(CreateTable $definition, array $columns): array
    {
        $keys = $this->inline($definition, $columns);
        $lowering = new KeyTermLowering();
        foreach ($definition->constraints as $run) {
            foreach ($run->items as $constraint) {
                if ($constraint instanceof TableCheck || $constraint instanceof ConstraintName) {
                    continue;
                }
                Check::input($constraint instanceof TablePrimaryKey || $constraint instanceof TableUnique, 'This table constraint has no complete CREATE TABLE representation.');
                $terms = [];
                foreach ($constraint->terms as $term) {
                    $terms[] = $lowering->lower($term, $columns);
                }
                $conflict = $constraint->conflict === null ? null : KeyConflict::from($constraint->conflict->value);
                $keys[] = $constraint instanceof TablePrimaryKey
                    ? new PrimaryKey($terms, false, $conflict, $constraint->autoincrement)
                    : new UniqueKey($terms, false, $conflict);
            }
        }

        return $keys;
    }

    /**
     * Finds inline keys using the owning column ordinal and collation.
     *
     * @param list<TableColumn> $columns
     * @return list<PrimaryKey|UniqueKey>
     */
    public function inline(CreateTable $definition, array $columns): array
    {
        $keys = [];
        foreach ($definition->columns as $slot => $column) {
            $collation = $columns[$slot]->collation;
            foreach ($column->constraints as $constraint) {
                if (!$constraint instanceof ColumnPrimaryKey && !$constraint instanceof ColumnUnique) {
                    continue;
                }
                $descending = $constraint instanceof ColumnPrimaryKey && $constraint->direction === SortDirection::Descending;
                $term = new KeyTerm($slot, $descending, $collation);
                $conflict = $constraint->conflict === null ? null : KeyConflict::from($constraint->conflict->value);
                $keys[] = $constraint instanceof ColumnPrimaryKey
                    ? new PrimaryKey([$term], true, $conflict, $constraint->autoincrement)
                    : new UniqueKey([$term], true, $conflict);
            }
        }

        return $keys;
    }
}
