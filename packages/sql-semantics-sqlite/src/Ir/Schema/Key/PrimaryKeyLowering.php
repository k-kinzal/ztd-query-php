<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Key;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TablePrimaryKey;

/**
 * Collects the single resolved primary key of a checked declaration.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class PrimaryKeyLowering
{
    /**
     * Checks the column and table constraint positions.
     * @throws InvalidConstruction When a constraint is outside the admitted primary key role
     */
    public function lower(CreateTable $definition): ?PrimaryKey
    {
        $key = $this->inline($definition);
        foreach ($definition->constraints as $run) {
            foreach ($run->items as $constraint) {
                Check::input($constraint instanceof TablePrimaryKey, 'This table constraint has no complete CREATE TABLE representation.');
                Check::input($key === null, 'A table declaration can have one primary key.');
                $terms = [];
                $lowering = new KeyTermLowering();
                foreach ($constraint->terms as $term) {
                    $terms[] = $lowering->lower($term, $definition->columns);
                }
                $key = new PrimaryKey($terms, false, KeyConflict::from($constraint->conflict->value ?? 'ABORT'), $constraint->autoincrement);
            }
        }

        return $key;
    }

    /**
     * Finds the inline key using its column's resolved ordinal.
     * @throws InvalidConstruction When an inline key cannot be represented
     */
    public function inline(CreateTable $definition): ?PrimaryKey
    {
        $key = null;
        foreach ($definition->columns as $slot => $column) {
            foreach ($column->constraints as $constraint) {
                if (!$constraint instanceof ColumnPrimaryKey) {
                    continue;
                }
                Check::input($key === null, 'A table declaration can have one primary key.');
                $term = new KeyTerm($slot, $constraint->direction === SortDirection::Descending);
                $key = new PrimaryKey([$term], true, KeyConflict::from($constraint->conflict->value ?? 'ABORT'), $constraint->autoincrement);
            }
        }

        return $key;
    }
}
