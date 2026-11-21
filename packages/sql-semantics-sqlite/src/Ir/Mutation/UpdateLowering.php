<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\From\QueryClauses;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Operation;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update as ParsedUpdate;

/**
 * Binds the writes and the old-row references of a checked UPDATE.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class UpdateLowering
{
    /**
     * Builds the single-target UPDATE in assignment order.
     * @throws InvalidConstruction When the conflict policy is outside the complete UPDATE domain
     */
    public function lower(Operation $operation, ParsedUpdate $statement): Update
    {
        Check::input(
            $statement->with === null && $statement->from === null && $statement->returning === [],
            'Complete SQLite UPDATE requires one table without WITH, FROM or RETURNING.',
        );
        $conflict = match ($statement->resolution) {
            null => UpdateConflict::Inherit,
            ConflictResolution::Abort => UpdateConflict::Abort,
            ConflictResolution::Ignore => UpdateConflict::Ignore,
            ConflictResolution::Fail, ConflictResolution::Replace, ConflictResolution::Rollback
                => throw new InvalidConstruction('This conflict policy has no complete SQLite UPDATE representation.'),
        };
        $binding = new SourceBinding($operation, $statement->target);
        $lowering = new ExpressionLowering();
        $assignments = [];
        foreach ($statement->assignments as $assignment) {
            Check::input($assignment instanceof Assignment, 'Complete SQLite UPDATE currently requires scalar assignments.');
            $assignments[] = new UpdateAssignment($binding->write($assignment->column), $lowering->lower($assignment->value, $binding));
        }
        $where = (new QueryClauses())->predicate($statement->where, $binding);
        $target = $binding->source();
        Check::invariant($target instanceof TableSource, 'A resolved UPDATE has one external target.');

        return new Update($target, $assignments, $where, $conflict);
    }
}
