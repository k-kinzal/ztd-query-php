<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertSelect as ParsedQueryInsert;

/**
 * Resolves the same writable layout for either INSERT input form.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class InsertTargetBinding
{
    /**
     * Returns the absolute target and its explicit writable slots.
     */
    public function lower(BoundStatement $operation, InsertRows|ParsedQueryInsert $statement): TableSource
    {
        Check::input($statement->columns !== [], 'An INSERT requires explicit target columns.');
        $names = [];
        foreach ($statement->columns as $column) {
            Check::input($column->steps === [], 'An INSERT column must name a declared target slot.');
            $names[] = $column->column->value;
        }

        return (new SourceBinding($operation, $statement->target))->writeTarget($names);
    }
}
