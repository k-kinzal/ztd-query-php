<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionSyntax;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\UtilityOption;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind;
use SqlSemantics\Rendering\Output;

/**
 * The rules of the option lists of EXPLAIN, VACUUM, ANALYZE, CLUSTER and REINDEX.
 *
 * Rule: PG-UTILITY-OPTION-001. Writing: a parenthesized list holds at least
 * one option; the older word syntax holds only the words the command reads
 * there, each once, without a value, in the order of the grammar, and the
 * word ANALYZE is the keyword. Checking: each command knows the option names
 * its manual page lists for the release (EXPLAIN gains SERIALIZE and MEMORY
 * in 17); the server compares the names exactly, after the lexer folded
 * unquoted words to lower case, and rejects any other name. A known option
 * whose value the server reads as a Boolean must have a Boolean value by
 * PG-UTILITY-OPTION-VALUE-001, and one it reads as text must have a value;
 * the options whose values follow rules of their own (FORMAT and SERIALIZE
 * of EXPLAIN, PARALLEL and INDEX_CLEANUP of VACUUM) are checked by the
 * command. Termination: one pass over a finite list.
 * Source: https://www.postgresql.org/docs/17/sql-explain.html, https://www.postgresql.org/docs/16/sql-explain.html,
 * https://www.postgresql.org/docs/17/sql-vacuum.html, https://www.postgresql.org/docs/17/sql-analyze.html,
 * https://www.postgresql.org/docs/17/sql-cluster.html, https://www.postgresql.org/docs/17/sql-reindex.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class OptionRules
{
    /**
     * The option names each command knows in every supported release.
     */
    private const KNOWN = [
        'EXPLAIN' => ['analyze', 'verbose', 'costs', 'settings', 'generic_plan', 'buffers', 'wal', 'timing', 'summary', 'format'],
        'VACUUM' => ['full', 'freeze', 'verbose', 'analyze', 'disable_page_skipping', 'skip_locked', 'index_cleanup', 'process_main', 'process_toast', 'truncate', 'parallel', 'skip_database_stats', 'only_database_stats', 'buffer_usage_limit'],
        'ANALYZE' => ['verbose', 'skip_locked', 'buffer_usage_limit'],
        'CLUSTER' => ['verbose'],
        'REINDEX' => ['concurrently', 'tablespace', 'verbose'],
    ];

    /**
     * The options of each command whose value is read as a Boolean.
     */
    private const BOOLEAN = [
        'EXPLAIN' => ['analyze', 'verbose', 'costs', 'settings', 'generic_plan', 'buffers', 'wal', 'timing', 'summary', 'memory'],
        'VACUUM' => ['full', 'freeze', 'verbose', 'analyze', 'disable_page_skipping', 'skip_locked', 'process_main', 'process_toast', 'truncate', 'skip_database_stats', 'only_database_stats'],
        'ANALYZE' => ['verbose', 'skip_locked'],
        'CLUSTER' => ['verbose'],
        'REINDEX' => ['concurrently', 'verbose'],
    ];

    /**
     * The options of each command whose value is read as text and must be written.
     */
    private const TEXT = ['EXPLAIN' => ['format'], 'VACUUM' => ['buffer_usage_limit'], 'ANALYZE' => ['buffer_usage_limit'], 'REINDEX' => ['tablespace']];

    /**
     * The option names a command gains in release 17.
     */
    private const ADDED = ['EXPLAIN' => ['serialize', 'memory']];

    /**
     * Tells whether options can be written in a syntax: a parenthesized list is not empty, and words are those the command reads, in order.
     *
     * @param list<UtilityOption> $options
     * @param list<string> $words The option names the word syntax of the command reads, in the order of the grammar
     */
    public function writable(array $options, OptionSyntax $syntax, array $words): bool
    {
        if ($syntax === OptionSyntax::Parenthesized) {
            return $options !== [];
        }
        $last = -1;
        foreach ($options as $option) {
            $position = array_search($option->option(), $words, true);
            $keyword = $option->name instanceof OptionKeyword;
            if (!is_int($position) || $position <= $last || $option->argument !== null || $keyword !== ($option->option() === 'analyze')) {
                return false;
            }
            $last = $position;
        }

        return true;
    }

    /**
     * Writes options in a syntax; no option writes nothing.
     *
     * @param list<UtilityOption> $options
     */
    public function write(Output $out, array $options, OptionSyntax $syntax): void
    {
        if ($options === []) {
            return;
        }
        if ($syntax === OptionSyntax::Parenthesized) {
            $out->symbol('(')->list($options)->symbol(')');

            return;
        }
        foreach ($options as $option) {
            $out->keyword($option->name instanceof OptionKeyword ? $option->name->value : strtoupper($option->option()));
        }
    }

    /**
     * Answers the last option of a name, or null when none is written.
     *
     * @param list<UtilityOption> $options
     */
    public function find(array $options, string $name): ?UtilityOption
    {
        $found = null;
        foreach ($options as $option) {
            if ($option->option() === $name) {
                $found = $option;
            }
        }

        return $found;
    }

    /**
     * Answers the option names a command knows in a release.
     *
     * @return list<string>
     */
    public function known(string $command, GrammarRelease $release): array
    {
        $known = self::KNOWN[$command] ?? [];

        return $release === GrammarRelease::PostgreSql166 ? $known : [...$known, ...(self::ADDED[$command] ?? [])];
    }

    /**
     * Derives the option values and reports each option the command does not know and each value it cannot read.
     *
     * @param list<UtilityOption> $options
     */
    public function derive(Derivation $derivation, string $command, array $options): void
    {
        $known = $this->known($command, $derivation->context->profile->grammar);
        foreach ($options as $option) {
            $option->deriveClause($derivation, $derivation->environment());
            $name = $option->option();
            if (!in_array($name, $known, true)) {
                $derivation->report(new UtilityProblem(UtilityProblemKind::UnknownOption, [$command, $name]));
            } elseif (in_array($name, self::BOOLEAN[$command] ?? [], true) && (new OptionArguments())->boolean($option) === null) {
                $derivation->report(new UtilityProblem(UtilityProblemKind::NotBoolean, [$name]));
            } elseif (in_array($name, self::TEXT[$command] ?? [], true) && $option->argument === null) {
                $derivation->report(new UtilityProblem(UtilityProblemKind::MissingArgument, [$name]));
            }
        }
    }
}
