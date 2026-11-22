<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\From\QueryClauses;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete as ParsedDelete;

/**
 * Resolves the supported top-level SQLite DELETE.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DeleteLowering
{
    /**
     * Constructs the write only after resolving its target and predicate.
     */
    public function lower(BoundStatement $operation, ParsedDelete $statement): Delete
    {
        Check::input($statement->with === null, 'Complete SQLite DELETE requires a single target without WITH.');
        $binding = new SourceBinding($operation, $statement->target);
        $where = (new QueryClauses())->predicate($statement->where, $binding);
        $returning = (new ReturningLowering())->lower($operation, $statement->returning, $binding);
        $target = $binding->source();
        Check::invariant($target instanceof TableSource, 'A resolved DELETE has one external target.');

        return new Delete($target, $where, $returning);
    }
}
