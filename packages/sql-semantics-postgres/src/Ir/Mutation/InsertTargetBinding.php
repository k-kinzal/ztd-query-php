<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\Destination;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
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
     * Closes the independent write and returned layouts over one target.
     * @return array{Destination, list<ReturnedColumn>}
     */
    public function lower(BoundStatement $operation, InsertRows|ParsedQueryInsert $statement): array
    {
        Check::input($statement->columns !== [], 'An INSERT requires explicit target columns.');
        $binding = new SourceBinding($operation, $statement->target);
        $written = [];
        foreach ($statement->columns as $column) {
            Check::input($column->steps === [], 'An INSERT column must name a declared target slot.');
            $written[] = $binding->slotFor($binding->writableColumn($column->column->value));
        }
        $returning = $statement->returning === [] ? [] : (new ReturningLowering())->lower($operation->facts->output, $binding);
        $relation = $binding->source();
        Check::input($relation !== null, 'An INSERT requires a resolved write target.');

        return [new Destination($relation, $written), $returning];
    }
}
