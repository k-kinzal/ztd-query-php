<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation;

use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;

/**
 * Keeps generation at the client SQL boundary, including scanner reachability.
 *
 * MySQL 5.6 and 5.7 also enter this rule while reading partition metadata from
 * .frm files. Their partition_entry action requires an existing part_info and
 * refuses every client invocation before reading its operands. The partition
 * clause rules remain available through CREATE TABLE and ALTER TABLE, and the
 * internal fragment can still be requested explicitly by its own rule name.
 * Source: https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/sql_yacc.yy#L5258-L5273.
 * MySQL 5.7 also reserves statement: parse_gcol_expr for .frm loading, while
 * allowing PARSE_GCOL_EXPR as an identifier in ordinary client SQL.
 * Source: https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/sql_yacc.yy#L6471-L6484.
 * The legacy keyword_sp rule also lists UDF_RETURNS_SYM, which neither lexer
 * emits. RETURNS is emitted as RETURNS_SYM and remains in the client grammar.
 * Source: https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/lex.h#L520.
 * A role name cannot consume LEX_HOSTNAME: that scanner state follows '@', but
 * role_ident_or_text only precedes the optional '@'; hosts use ident_or_text.
 * Source: https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc#L1928-L1950.
 * Source: https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy#L15144-L15149.
 * COUNT_SYM is a function token and requires a following '('. The LOAD count
 * clause instead follows COUNT with NUM and consumes the IDENT_sys alternative.
 * Source: https://github.com/mysql/mysql-server/blob/mysql-8.0.44/sql/lex.h#L835.
 * Source: https://github.com/mysql/mysql-server/blob/mysql-8.0.44/sql/sql_yacc.yy#L13700-L13711.
 *
 * @visibility root
 */
final class GrammarAdaptation
{
    /**
     * Excludes internal-only and lexer-unreachable alternatives, preserving source identities.
     * Raw diagnostic generation can continue to use MySqlGrammar::load().
     */
    public function adapted(Grammar $grammar): Grammar
    {
        $rules = $grammar->ruleMap;
        foreach (['statement', 'keyword_sp', 'role_ident_or_text', 'opt_source_count'] as $name) {
            $rule = $rules[$name] ?? null;
            if ($rule === null) {
                continue;
            }
            $alternatives = array_values(array_filter($rule->alternatives, static fn (Production $production): bool => match ($name) {
                'statement' => count($production->symbols) !== 1 || !in_array($production->nonTerminalAt(0)?->value, ['partition_entry', 'parse_gcol_expr'], true),
                'keyword_sp' => $production->terminalAt(0)?->value !== 'UDF_RETURNS_SYM',
                'role_ident_or_text' => $production->terminalAt(0)?->value !== 'LEX_HOSTNAME',
                'opt_source_count' => $production->terminalAt(0)?->value !== 'COUNT_SYM',
            }));
            if ($alternatives !== $rule->alternatives) {
                $rules[$name] = new ProductionRule($name, $alternatives);
            }
        }
        return $rules === $grammar->ruleMap ? $grammar : new Grammar($grammar->startSymbol, $rules, $grammar->aliases);
    }
}
