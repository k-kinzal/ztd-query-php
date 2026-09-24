<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility\Maintenance;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\AnalyzeOptions;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\BufferUsageLimit;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\IndexCleanup;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\VacuumOptions;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Converts VACUUM and ANALYZE option lists and legacy keywords to their typed options.
 * @visibility SqlSemantics
 */
final class MaintenanceSettings
{
    /**
     * Unknown options, arguments outside their domains and incompatible combinations are rejected as the server does.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function vacuum(Node $source, Identifiers $identifiers): VacuumOptions
    {
        $flags = self::legacy($source);
        $buffer = null;
        $parallel = null;
        $cleanup = null;
        foreach (UtilityOptions::read($source, $identifiers) as $name => $option) {
            match ($name) {
                'verbose', 'skip_locked', 'analyze', 'freeze', 'full', 'disable_page_skipping', 'process_main', 'process_toast', 'truncate', 'skip_database_stats', 'only_database_stats' => $flags[$name] = UtilityOptions::boolean($option),
                'buffer_usage_limit' => $buffer = UtilityOptions::bufferUsageLimit($option),
                'parallel' => $parallel = UtilityOptions::integer($option),
                'index_cleanup' => $cleanup = $option[0] === null || strtolower((string) $option[0]) === 'auto' ? IndexCleanup::Auto : (UtilityOptions::boolean($option) ? IndexCleanup::On : IndexCleanup::Off),
                default => throw new InvalidSql(InputViolation::MaintenanceOption, $option[1]),
            };
        }
        return self::build($flags, $buffer, $parallel, $cleanup, $source);
    }

    /**
     * Applies the server defaults to the options that were not written.
     * @param array<string, bool> $flags Written Boolean options by name
     * @throws InvalidSql
     */
    public static function build(array $flags, ?BufferUsageLimit $buffer, ?int $parallel, ?IndexCleanup $cleanup, Node $source): VacuumOptions
    {
        try {
            return new VacuumOptions(
                verbose: $flags['verbose'] ?? false,
                skipLocked: $flags['skip_locked'] ?? false,
                bufferUsageLimit: $buffer,
                analyze: $flags['analyze'] ?? false,
                freeze: $flags['freeze'] ?? false,
                full: $flags['full'] ?? false,
                disablePageSkipping: $flags['disable_page_skipping'] ?? false,
                indexCleanup: $cleanup,
                processMain: $flags['process_main'] ?? true,
                processToast: $flags['process_toast'] ?? true,
                truncate: $flags['truncate'] ?? null,
                parallel: $parallel,
                skipDatabaseStats: $flags['skip_database_stats'] ?? false,
                onlyDatabaseStats: $flags['only_database_stats'] ?? false,
            );
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::MaintenanceOption, $source, $error);
        }
    }

    /**
     * ANALYZE accepts only VERBOSE, SKIP_LOCKED and BUFFER_USAGE_LIMIT.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function analyze(Node $source, Identifiers $identifiers): AnalyzeOptions
    {
        $verbose = self::present($source, 'opt_verbose');
        $skipLocked = false;
        $buffer = null;
        foreach (UtilityOptions::read($source, $identifiers) as $name => $option) {
            match ($name) {
                'verbose' => $verbose = UtilityOptions::boolean($option),
                'skip_locked' => $skipLocked = UtilityOptions::boolean($option),
                'buffer_usage_limit' => $buffer = UtilityOptions::bufferUsageLimit($option),
                default => throw new InvalidSql(InputViolation::MaintenanceOption, $option[1]),
            };
        }
        return new AnalyzeOptions($verbose, $skipLocked, $buffer);
    }

    /**
     * Reads the legacy VACUUM keywords FULL, FREEZE, VERBOSE and ANALYZE.
     * @return array<string, bool>
     */
    public static function legacy(Node $source): array
    {
        return array_filter([
            'full' => self::present($source, 'opt_full'),
            'freeze' => self::present($source, 'opt_freeze'),
            'verbose' => self::present($source, 'opt_verbose'),
            'analyze' => self::present($source, 'opt_analyze'),
        ], static fn (bool $present): bool => $present);
    }

    /**
     * Reports whether an optional keyword was written.
     */
    public static function present(Node $source, string $rule): bool
    {
        return (Tree::child($source, [$rule])?->tokens() ?? []) !== [];
    }
}
