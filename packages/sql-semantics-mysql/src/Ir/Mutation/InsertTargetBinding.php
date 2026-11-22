<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

/**
 * Resolves the same writable layout for either INSERT input form.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class InsertTargetBinding
{
    /**
     * Returns the absolute target and its explicit writable slots.
     */
    public function lower(BoundStatement $operation, InsertInto $into): TableSource
    {
        Check::input($into->columns !== null && $into->columns->columns !== [], 'An INSERT requires explicit target columns.');
        $names = [];
        foreach ($into->columns->columns as $column) {
            Check::input($column instanceof ColumnUse, 'An INSERT column must name a declared target slot.');
            $names[] = $column->name->value;
        }

        return (new SourceBinding($operation, $into->table))->writeTarget($names);
    }
}
