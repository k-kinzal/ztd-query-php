<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\From\SourceDeclarations;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesLowering;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Operation;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;

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
    public function lower(Operation $operation, InsertRows $statement): Insert
    {
        $into = $statement->into;
        Check::input(
            $into->with === null && !$into->replace && $statement->upserts === [] && $statement->returning === [],
            'Complete SQLite INSERT does not support WITH, REPLACE, UPSERT or RETURNING.',
        );
        $policy = match ($into->resolution) {
            null => InsertConflict::Inherit,
            ConflictResolution::Abort => InsertConflict::Abort,
            ConflictResolution::Ignore => InsertConflict::Ignore,
            ConflictResolution::Fail, ConflictResolution::Replace, ConflictResolution::Rollback
                => throw new InvalidConstruction('This conflict policy has no complete SQLite INSERT representation.'),
        };
        $targets = (new SourceDeclarations())->bind($operation, $into->target);
        Check::invariant(isset($targets[0]), 'A resolved INSERT has a target.');
        [, $table] = $targets[0];
        $columns = [];
        foreach ($into->columns as $name) {
            $matching = $table->matchingColumns($name->value, Comparison::AsciiInsensitive);
            Check::input(
                count($matching) === 1 && in_array($matching[0], $table->columns, true) && !$matching[0]->generated,
                'An INSERT column must resolve to a writable declared column.',
            );
            $columns[] = new Identifier($matching[0]->name->value);
        }
        $target = new TableSource(
            new Identifier(($table->name->schema ?? $operation->context->declarationSchema)->value),
            new Identifier($table->name->name->value),
            $columns,
        );

        return new Insert($target, (new ValuesLowering())->lower($operation, $statement->rows), $policy);
    }
}
