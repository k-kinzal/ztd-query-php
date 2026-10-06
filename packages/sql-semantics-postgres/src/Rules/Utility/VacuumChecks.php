<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\UtilityOption;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind;

/**
 * Checks the option values of VACUUM and the combinations the server refuses.
 *
 * Rule: PG-VACUUM-OPTIONS-001. PARALLEL takes an integer from 0 to 1024
 * and must have a value. INDEX_CLEANUP without a value or with `auto`,
 * compared without regard to case, leaves the choice to the server; any
 * other value must be Boolean. The Boolean options are read by
 * PG-UTILITY-OPTION-VALUE-001; PROCESS_MAIN and PROCESS_TOAST are on unless
 * turned off. The server refuses: BUFFER_USAGE_LIMIT with FULL unless
 * ANALYZE is on; FULL with PARALLEL above 0; a column list without ANALYZE;
 * FULL with DISABLE_PAGE_SKIPPING; FULL with PROCESS_TOAST off;
 * ONLY_DATABASE_STATS with tables, or with any of ANALYZE, FREEZE, FULL,
 * SKIP_LOCKED, DISABLE_PAGE_SKIPPING and SKIP_DATABASE_STATS on.
 * Termination: one pass over the options.
 * Source: https://www.postgresql.org/docs/17/sql-vacuum.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class VacuumChecks
{
    /**
     * The options ONLY_DATABASE_STATS cannot be combined with when they are on.
     */
    private const EXCLUSIVE = ['analyze', 'freeze', 'full', 'skip_locked', 'disable_page_skipping', 'skip_database_stats'];

    /**
     * The largest number of parallel workers VACUUM takes.
     */
    private const WORKERS = 1024;

    /**
     * Reports each value VACUUM cannot read and each combination of options and tables it refuses.
     *
     * @param list<UtilityOption> $options
     * @param bool $tables Whether tables are named
     * @param bool $columns Whether a table lists columns
     */
    public function check(Derivation $derivation, array $options, bool $tables, bool $columns): void
    {
        $values = new OptionArguments();
        $workers = 0;
        foreach ($options as $option) {
            if ($option->option() === 'parallel') {
                $workers = $this->workers($derivation, $option);
            } elseif ($option->option() === 'index_cleanup' && $option->argument !== null && strtolower($option->text() ?? '') !== 'auto' && $values->boolean($option) === null) {
                $derivation->report(new UtilityProblem(UtilityProblemKind::NotBoolean, ['index_cleanup']));
            }
        }
        $full = $values->enabled($options, 'full', false);
        $analyze = $values->enabled($options, 'analyze', false);
        $problems = [
            [UtilityProblemKind::BufferLimitFull, $full && !$analyze && (new OptionRules())->find($options, 'buffer_usage_limit')?->argument !== null],
            [UtilityProblemKind::ParallelFull, $full && $workers > 0],
            [UtilityProblemKind::ColumnsWithoutAnalyze, $columns && !$analyze],
            [UtilityProblemKind::PageSkippingFull, $full && $values->enabled($options, 'disable_page_skipping', false)],
            [UtilityProblemKind::ToastFull, $full && !$values->enabled($options, 'process_toast', true)],
        ];
        if ($values->enabled($options, 'only_database_stats', false)) {
            $problems[] = [UtilityProblemKind::DatabaseStatsTables, $tables];
            $problems[] = [UtilityProblemKind::DatabaseStatsOptions, !$tables && $this->combined($options)];
        }
        foreach ($problems as [$kind, $broken]) {
            if ($broken) {
                $derivation->report(new UtilityProblem($kind));
            }
        }
    }

    /**
     * Answers the number of workers a PARALLEL option asks for, reporting a missing, non-integer or out-of-range value; 0 when there is no valid one.
     */
    public function workers(Derivation $derivation, UtilityOption $option): int
    {
        if ($option->argument === null) {
            $derivation->report(new UtilityProblem(UtilityProblemKind::ParallelWithoutValue));

            return 0;
        }
        $workers = (new OptionArguments())->integer($option);
        if ($workers === null) {
            $derivation->report(new UtilityProblem(UtilityProblemKind::NotInteger, ['parallel']));

            return 0;
        }
        if ($workers < 0 || $workers > self::WORKERS) {
            $derivation->report(new UtilityProblem(UtilityProblemKind::ParallelRange));

            return 0;
        }

        return $workers;
    }

    /**
     * Tells whether an option ONLY_DATABASE_STATS excludes is on.
     *
     * @param list<UtilityOption> $options
     */
    public function combined(array $options): bool
    {
        $values = new OptionArguments();
        foreach (self::EXCLUSIVE as $name) {
            if ($values->enabled($options, $name, false)) {
                return true;
            }
        }

        return false;
    }
}
