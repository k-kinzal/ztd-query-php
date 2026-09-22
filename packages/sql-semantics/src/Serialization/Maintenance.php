<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization;

use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Maintenance as Statement;

/**

 * Writes explicit maintenance targets and attachment expressions. @visibility SqlSemantics

 */
final class Maintenance
{
    public static function write(Statement\ReindexAllStatement|Statement\ReindexNamedStatement|Statement\ReindexObjectStatement|Statement\ReindexDatabaseStatement|Statement\AnalyzeAllStatement|Statement\AnalyzeNamedStatement|Statement\VacuumDatabaseStatement|Statement\VacuumIntoStatement|Statement\AttachDatabaseStatement|Statement\DetachDatabaseStatement|Statement\UseDatabaseStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        return match (true) {
            $statement instanceof Statement\ReindexAllStatement => Build::keyword('REINDEX'),
            $statement instanceof Statement\ReindexNamedStatement => new Tree('reindex', [Build::keyword('REINDEX'), Build::identifier($statement->target->parts, $dialect)]),
            $statement instanceof Statement\ReindexObjectStatement,
            $statement instanceof Statement\ReindexDatabaseStatement => self::reindex($statement),
            $statement instanceof Statement\AnalyzeAllStatement => Build::keyword('ANALYZE'),
            $statement instanceof Statement\AnalyzeNamedStatement => new Tree('analyze', [Build::keyword('ANALYZE'), Build::identifier($statement->target->parts, $dialect)]),
            $statement instanceof Statement\VacuumDatabaseStatement => new Tree('vacuum', [Build::keyword('VACUUM'), ...($statement->schema === null ? [] : [Build::identifier([$statement->schema], $dialect)])]),
            $statement instanceof Statement\VacuumIntoStatement => new Tree('vacuum-into', [Build::keyword('VACUUM'), ...($statement->schema === null ? [] : [Build::identifier([$statement->schema], $dialect)]), Build::keyword('INTO'), Expressions::write($statement->destination)]),
            $statement instanceof Statement\AttachDatabaseStatement => new Tree('attach', [Build::keyword('ATTACH DATABASE'), Expressions::write($statement->database), Build::keyword('AS'), Expressions::write($statement->schema), ...($statement->encryptionKey === null ? [] : [Build::keyword('KEY'), Expressions::write($statement->encryptionKey)])]),
            $statement instanceof Statement\DetachDatabaseStatement => new Tree('detach', [Build::keyword('DETACH DATABASE'), Expressions::write($statement->schema)]),
            $statement instanceof Statement\UseDatabaseStatement => new Tree('use', [Build::keyword('USE'), Build::identifier($statement->database->parts, $dialect)]),
        };
    }

    /**
     * Writes only the option domains applicable to PostgreSQL index rebuilding.
     */
    public static function reindex(Statement\ReindexObjectStatement|Statement\ReindexDatabaseStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        $options = $statement->options;
        $parts = [...($options->concurrently ? [Build::keyword('CONCURRENTLY')] : []), ...($options->verbose ? [Build::keyword('VERBOSE')] : []), ...($options->tablespace === null ? [] : [new Tree('tablespace', [Build::keyword('TABLESPACE'), Build::identifier([$options->tablespace], $dialect)])])];
        $target = $statement instanceof Statement\ReindexObjectStatement ? [Build::keyword($statement->targetKind->value), Build::identifier($statement->target->parts, $dialect)] : [Build::keyword($statement->selection->value), ...($statement->database === null ? [] : [Build::identifier([$statement->database], $dialect)])];
        return new Tree('reindex', [Build::keyword('REINDEX'), ...($parts === [] ? [] : [Build::parentheses(Build::separated($parts))]), ...$target]);
    }
}
