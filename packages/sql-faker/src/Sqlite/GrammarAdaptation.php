<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;

/**
 * Exposes statement aliases for SQLite's cmd alternatives without filtering the upstream grammar.
 * @visibility root
 */
final class GrammarAdaptation
{
    /**
     * Answers the grammar with every adaptation applied.
     *
     * @param Grammar $grammar Grammar as SQLite's parser declares it
     *
     * @return Grammar Grammar a generator can be aimed at
     */
    public function adapted(Grammar $grammar): Grammar
    {
        $cmd = $grammar->ruleMap['cmd'] ?? null;
        return $cmd === null ? $grammar : new Grammar($grammar->startSymbol, $this->withStatementRules($grammar->ruleMap, $cmd));
    }

    /**
     * Gives each statement kind embedded in `cmd` a rule of its own.
     *
     * Build-time conditionals have already been resolved by LemonParser.
     * Every selected source alternative remains reachable through its alias.
     *
     * @param array<string, ProductionRule> $ruleMap Rules to add the statement rules to
     * @param ProductionRule $cmd The `cmd` rule every statement is an alternative of
     *
     * @return array<string, ProductionRule> Rules a statement kind can be named in
     */
    public function withStatementRules(array $ruleMap, ProductionRule $cmd): array
    {
        $groups = $this->statementAlternatives($cmd);
        foreach (['insert', 'delete', 'update', 'drop_table', 'alter_table'] as $statement) {
            if ($groups[$statement] !== []) {
                $ruleMap[$statement] = new ProductionRule($statement, $groups[$statement]);
            }
        }

        return $ruleMap;
    }

    /**
     * Sorts the alternatives of `cmd` by the statement each one writes.
     *
     * An alternative announces itself either with a keyword or, when it begins
     * with an optional WITH clause, with the keyword right after it. ALTER and
     * DROP say what they act on in their second word, and only the ones acting
     * on a table are wanted here.
     *
     * @param ProductionRule $cmd The `cmd` rule every statement is an alternative of
     *
     * @return array<string, list<Production>> Alternatives by statement kind
     */
    public function statementAlternatives(ProductionRule $cmd): array
    {
        $groups = [
            'insert' => [],
            'delete' => [],
            'update' => [],
            'drop_table' => [],
            'alter_table' => [],
        ];

        foreach ($cmd->alternatives as $alternative) {
            $leading = $alternative->terminalAt(0);
            if ($leading !== null) {
                match ($leading->value) {
                    'DELETE' => $groups['delete'][] = $alternative,
                    'UPDATE' => $groups['update'][] = $alternative,
                    'ALTER' => $alternative->terminalAt(1)?->value === 'TABLE'
                        ? $groups['alter_table'][] = $alternative
                        : null,
                    'DROP' => $alternative->terminalAt(1)?->value === 'TABLE'
                        ? $groups['drop_table'][] = $alternative
                        : null,
                    default => null,
                };

                continue;
            }

            $second = $alternative->nonTerminalAt(0) === null ? null : $alternative->terminalAt(1);
            if ($second !== null) {
                match ($second->value) {
                    'DELETE' => $groups['delete'][] = $alternative,
                    'UPDATE' => $groups['update'][] = $alternative,
                    default => null,
                };

                continue;
            }

            if ($alternative->nonTerminalAt(0) !== null && $alternative->nonTerminalAt(1)?->value === 'insert_cmd') {
                $groups['insert'][] = $alternative;
            }
        }

        return $groups;
    }

}
