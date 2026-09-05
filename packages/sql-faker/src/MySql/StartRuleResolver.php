<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use SqlFaker\Grammar\Grammar;

/**
 * Resolves MySQL rule names across grammar releases.
 */
final class StartRuleResolver
{
    /**
 * @param Grammar $grammar Grammar whose rule names are resolved
 */
    public function __construct(private readonly Grammar $grammar)
    {
    }

    /**
     * Answers which of this grammar's rules a requested start rule is grown from.
     *
     * MySQL renamed its top-level rules across releases, so the name a caller
     * asks for may not be the name this release declares. A request the
     * grammar knows is taken as it stands; otherwise the rule that release
     * calls the same thing is used, and a request nothing matches is handed
     * back for the derivation to report.
     *
     * @param string|null $requested Rule the caller asked for, or null for the grammar's own entry point
     *
     * @return string Rule this grammar declares
     */
    public function startSymbolFor(?string $requested): string
    {
        if ($requested === null) {
            if (isset($this->grammar->ruleMap['simple_statement_or_begin'])) {
                return 'simple_statement_or_begin';
            }

            return isset($this->grammar->ruleMap['statement']) ? 'statement' : $this->grammar->startSymbol;
        }
        if (isset($this->grammar->ruleMap[$requested])) {
            return $requested;
        }

        $fallbacks = [
            'select_stmt' => 'select',
            'insert_stmt' => 'insert',
            'update_stmt' => 'update',
            'delete_stmt' => 'delete',
            'create_table_stmt' => 'create',
            'alter_table_stmt' => 'alter',
            'drop_table_stmt' => 'drop',
            'simple_statement' => 'statement',
            'simple_statement_or_begin' => $this->grammar->startSymbol,
        ];
        $fallback = $fallbacks[$requested] ?? $requested;

        return isset($this->grammar->ruleMap[$fallback]) ? $fallback : $requested;
    }
}
