<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesLowering;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Select as CheckedSelect;
use SqlSemantics\Platform\Sqlite\Ir\SelectLowering;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertDefaults;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

/**
 * Resolves the writable slots and the conflict policy of a checked INSERT.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class InsertLowering
{
    /**
     * Constructs the write from the target declaration and from independent input rows.
     *
     * @throws InvalidConstruction When the INSERT is outside this domain
     */
    public function lower(BoundStatement $operation, InsertRows|InsertSelect|InsertDefaults $statement): Insert
    {
        $into = $statement->into;
        Check::input(
            $into->with === null && !$into->replace && ($statement instanceof InsertDefaults || $statement->upserts === []) && $statement->returning === [],
            'Complete SQLite INSERT does not support WITH, REPLACE, UPSERT or RETURNING.',
        );
        $policy = match ($into->resolution) {
            null => InsertConflict::Inherit,
            ConflictResolution::Abort => InsertConflict::Abort,
            ConflictResolution::Ignore => InsertConflict::Ignore,
            ConflictResolution::Fail, ConflictResolution::Replace, ConflictResolution::Rollback
                => throw new InvalidConstruction('This conflict policy has no complete SQLite INSERT representation.'),
        };
        $target = (new InsertTargetLowering())->lower($operation, $into, $statement instanceof InsertDefaults);

        return new Insert($target, $this->input($operation, $statement), $policy);
    }

    /**
     * Resolves the checked input role independently of the write target.
     *
     * @throws InvalidConstruction When the input is outside the checked query forms
     */
    public function input(BoundStatement $operation, InsertRows|InsertSelect|InsertDefaults $statement): ValuesQuery|CheckedSelect|DefaultRow
    {
        if ($statement instanceof InsertDefaults) {
            return new DefaultRow();
        }
        if ($statement instanceof InsertRows) {
            return (new ValuesLowering())->lower($operation, $statement->rows);
        }
        $query = $statement->query;
        Check::input($query instanceof Select, 'A query INSERT requires an independent ordinary SELECT.');

        return (new SelectLowering())->lower($operation, $query);
    }
}
