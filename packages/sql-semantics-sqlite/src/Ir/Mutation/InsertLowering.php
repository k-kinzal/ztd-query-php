<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesLowering;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Select as CheckedSelect;
use SqlSemantics\Platform\Sqlite\Ir\SelectLowering;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
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
        Check::input($into->with === null, 'Complete SQLite INSERT does not support WITH.');
        $policy = match ($into->replace ? ConflictResolution::Replace : $into->resolution) {
            null => InsertConflict::Inherit,
            ConflictResolution::Abort => InsertConflict::Abort,
            ConflictResolution::Ignore => InsertConflict::Ignore,
            ConflictResolution::Fail => InsertConflict::Fail,
            ConflictResolution::Rollback => InsertConflict::Rollback,
            ConflictResolution::Replace => InsertConflict::Replace,
        };
        $binding = new SourceBinding($operation, $into->target);
        $writes = (new InsertTargetLowering())->lower($binding, $into, $statement instanceof InsertDefaults);
        $returning = (new ReturningLowering())->lower($operation, $statement->returning, $binding);
        $target = $binding->source();
        Check::invariant($target instanceof TableSource, 'A resolved INSERT has one external target.');

        return new Insert($target, $writes, $this->input($operation, $statement), $policy, $returning, $this->handler($statement));
    }

    /**
     * Resolves the uniqueness handler from the supported statement forms.
     */
    public function handler(InsertRows|InsertSelect|InsertDefaults $statement): InsertConflictHandler
    {
        if ($statement instanceof InsertDefaults || $statement->upserts === []) {
            return InsertConflictHandler::None;
        }
        $clause = $statement->upserts[0];
        Check::input(
            count($statement->upserts) === 1 && $clause->target === null && $clause->assignments === [] && $clause->where === null,
            'A complete INSERT requires a single targetless DO NOTHING clause.',
        );

        return InsertConflictHandler::DoNothing;
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
