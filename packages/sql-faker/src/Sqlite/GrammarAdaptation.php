<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;

/**
 * Reshapes SQLite's parser grammar into one a generator can be aimed at.
 *
 * `parse.y` is written for a parser, which only ever has to recognise what it
 * is given. A generator is asked for a DELETE, and SQLite's grammar has no
 * rule by that name — DELETE, UPDATE, INSERT, ALTER TABLE and DROP TABLE all
 * live as alternatives of `cmd`. It also names constructs that its own parser
 * accepts but its tokenizer will not read back, and one table option that
 * only appears as a generic identifier. Every difference between the grammar
 * SQLite parses with and the grammar this package generates from is recorded
 * here, so a reader can see the whole of it in one place.
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
        $ruleMap = $this->withStrictTableOption($grammar->ruleMap);
        $cmd = $ruleMap['cmd'] ?? null;
        if ($cmd === null) {
            return new Grammar($grammar->startSymbol, $ruleMap);
        }

        $ruleMap = $this->withoutWithinGroupExpressions($ruleMap);
        $ruleMap = $this->withoutFrameOnlyWindows($ruleMap);
        $ruleMap = $this->withStatementRules($ruleMap, $cmd);

        return new Grammar($grammar->startSymbol, $ruleMap);
    }

    /**
     * Names STRICT as its own table option so generation can deliberately reach it.
     *
     * SQLite's grammar treats every table option as a generic identifier, so a
     * generator aiming at `table_option` would only ever invent a name. A fixed
     * alternative gives it the one spelling that means something.
     *
     * @param array<string, ProductionRule> $ruleMap Rules as SQLite declares them
     *
     * @return array<string, ProductionRule> Rules with STRICT reachable
     */
    public function withStrictTableOption(array $ruleMap): array
    {
        $tableOption = $ruleMap['table_option'] ?? null;
        if ($tableOption === null) {
            return $ruleMap;
        }

        $alternatives = $tableOption->alternatives;
        $alternatives[] = new Production([new Terminal(LexicalGrammar::STRICT_TABLE_OPTION)]);
        $ruleMap['table_option'] = new ProductionRule('table_option', $alternatives);

        return $ruleMap;
    }

    /**
     * Gives each statement kind embedded in `cmd` a rule of its own.
     *
     * A DELETE that carries an ORDER BY is only accepted by a build compiled
     * with SQLITE_ENABLE_UPDATE_DELETE_LIMIT, so it is left out of the rule a
     * caller asking for a DELETE is aimed at.
     *
     * @param array<string, ProductionRule> $ruleMap Rules to add the statement rules to
     * @param ProductionRule $cmd The `cmd` rule every statement is an alternative of
     *
     * @return array<string, ProductionRule> Rules a statement kind can be named in
     */
    public function withStatementRules(array $ruleMap, ProductionRule $cmd): array
    {
        $groups = $this->statementAlternatives($cmd);
        $groups['delete'] = array_values(array_filter(
            $groups['delete'],
            static fn (Production $alternative): bool => !$alternative->hasNonTerminal('orderby_opt'),
        ));

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

    /**
     * Drops the expressions SQLite parses but cannot tokenize back.
     *
     * WITHIN GROUP is accepted by the grammar and rejected by the tokenizer,
     * so an expression carrying it could never be read back as itself.
     *
     * @param array<string, ProductionRule> $ruleMap Rules to filter
     *
     * @return array<string, ProductionRule> Rules whose expressions all read back
     */
    public function withoutWithinGroupExpressions(array $ruleMap): array
    {
        $expr = $ruleMap['expr'] ?? null;
        if ($expr === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $expr->alternatives,
            static fn (Production $alternative): bool => !$alternative->hasTerminal('WITHIN'),
        ));
        if ($filtered !== []) {
            $ruleMap['expr'] = new ProductionRule('expr', $filtered);
        }

        return $ruleMap;
    }

    /**
     * Drops the window definitions that write nothing of their own.
     *
     * @param array<string, ProductionRule> $ruleMap Rules to filter
     *
     * @return array<string, ProductionRule> Rules whose windows always write something
     */
    public function withoutFrameOnlyWindows(array $ruleMap): array
    {
        $window = $ruleMap['window'] ?? null;
        if ($window === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $window->alternatives,
            fn (Production $alternative): bool => !$this->isFrameOnlyWindow($alternative),
        ));
        if ($filtered !== []) {
            $ruleMap['window'] = new ProductionRule('window', $filtered);
        }

        return $ruleMap;
    }

    /**
     * Reports whether a window alternative is nothing but an optional frame.
     *
     * Such an alternative can expand to the empty string, which leaves an OVER
     * with nothing after it, so a generator aiming at a window would be able
     * to write SQL SQLite cannot parse.
     *
     * @param Production $alternative Window alternative to judge
     *
     * @return bool True when it carries no keyword of its own
     */
    public function isFrameOnlyWindow(Production $alternative): bool
    {
        $nonTerminals = $alternative->nonTerminalNames();

        return !$alternative->hasAnyTerminal()
            && in_array('frame_opt', $nonTerminals, true)
            && array_diff($nonTerminals, ['nm', 'frame_opt']) === [];
    }
}
