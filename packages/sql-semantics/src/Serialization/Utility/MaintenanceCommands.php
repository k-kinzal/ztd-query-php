<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Utility;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes PostgreSQL VACUUM, ANALYZE and CLUSTER with parenthesized options that differ from the server defaults.
 * @visibility SqlSemantics
 */
final class MaintenanceCommands
{
    /**
     * Returns null for statements outside these maintenance commands.
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\VacuumStatement => new Tree('vacuum', [Build::keyword('VACUUM'), ...self::options(self::vacuum($statement->options)), ...self::targets($statement->targets)]),
            $statement instanceof Statement\AnalyzeStatement => new Tree('analyze', [Build::keyword('ANALYZE'), ...self::options(self::analyze($statement->options)), ...self::targets($statement->targets)]),
            $statement instanceof Statement\ClusterAllStatement => new Tree('cluster', [Build::keyword('CLUSTER'), ...self::options($statement->verbose ? [Build::keyword('VERBOSE')] : [])]),
            $statement instanceof Statement\ClusterTableStatement => new Tree('cluster', [
                Build::keyword('CLUSTER'),
                ...self::options($statement->verbose ? [Build::keyword('VERBOSE')] : []),
                Relations::target($statement->table, Dialect::PostgreSql),
                ...($statement->index === null ? [] : [Build::keyword('USING'), Build::identifier([$statement->index], Dialect::PostgreSql)]),
            ]),
            default => null,
        };
    }

    /**
     * @return list<Tree> Options that differ from the server defaults, in the documented order
     * @throws InvalidStructure
     */
    public static function vacuum(Statement\VacuumOptions $options): array
    {
        return [
            ...($options->full ? [Build::keyword('FULL')] : []),
            ...($options->freeze ? [Build::keyword('FREEZE')] : []),
            ...($options->verbose ? [Build::keyword('VERBOSE')] : []),
            ...($options->analyze ? [Build::keyword('ANALYZE')] : []),
            ...($options->disablePageSkipping ? [Build::keyword('DISABLE_PAGE_SKIPPING')] : []),
            ...($options->skipLocked ? [Build::keyword('SKIP_LOCKED')] : []),
            ...($options->indexCleanup === null ? [] : [Build::keyword('INDEX_CLEANUP ' . $options->indexCleanup->value)]),
            ...($options->processMain ? [] : [Build::keyword('PROCESS_MAIN FALSE')]),
            ...($options->processToast ? [] : [Build::keyword('PROCESS_TOAST FALSE')]),
            ...($options->truncate === null ? [] : [Build::keyword('TRUNCATE ' . ($options->truncate ? 'TRUE' : 'FALSE'))]),
            ...($options->parallel === null ? [] : [Build::keyword('PARALLEL ' . $options->parallel)]),
            ...($options->skipDatabaseStats ? [Build::keyword('SKIP_DATABASE_STATS')] : []),
            ...($options->onlyDatabaseStats ? [Build::keyword('ONLY_DATABASE_STATS')] : []),
            ...self::buffer($options->bufferUsageLimit),
        ];
    }

    /**
     * @return list<Tree> Options that differ from the server defaults
     * @throws InvalidStructure
     */
    public static function analyze(Statement\AnalyzeOptions $options): array
    {
        return [
            ...($options->verbose ? [Build::keyword('VERBOSE')] : []),
            ...($options->skipLocked ? [Build::keyword('SKIP_LOCKED')] : []),
            ...self::buffer($options->bufferUsageLimit),
        ];
    }

    /**
     * @return list<Tree>
     * @throws InvalidStructure
     */
    public static function buffer(?Statement\BufferUsageLimit $limit): array
    {
        return $limit === null ? [] : [new Tree('buffer-usage-limit', [Build::keyword('BUFFER_USAGE_LIMIT'), DatabaseCommands::value($limit->setting)])];
    }

    /**
     * Writes a parenthesized option list, or nothing when every option has its default.
     * @param list<Tree> $options
     * @return list<Tree>
     */
    public static function options(array $options): array
    {
        return $options === [] ? [] : [Build::parentheses(Build::separated($options))];
    }

    /**
     * @param list<Statement\MaintenanceTarget> $targets
     * @return list<Tree>
     */
    public static function targets(array $targets): array
    {
        return $targets === [] ? [] : [Build::separated(array_map(static fn (Statement\MaintenanceTarget $target): Tree => new Tree('maintenance-target', [
            Relations::target($target->table, Dialect::PostgreSql),
            ...($target->columns === [] ? [] : [Build::parentheses(Build::separated(array_map(static fn (string $column): Tree => Build::identifier([$column], Dialect::PostgreSql), $target->columns)))]),
        ]), $targets))];
    }
}
