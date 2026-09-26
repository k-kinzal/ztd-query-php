<?php

declare(strict_types=1);

use SqlParser\Grammar\Grammar;
use SqlParser\Resource\VersionRegistry;

/**
 * Describes complete lexical spellings without running a lexer during updates.
 *
 * @return array<string, string>
 */
function lexicalPatterns(string $dialect): array
{
    $word = '[A-Za-z_\x80-\xFF][A-Za-z_0-9$\x80-\xFF]*';
    $integer = '[0-9](?:_?[0-9])*';
    $number = "(?:{$integer}(?:\\.(?:{$integer})?)?|\\.{$integer})(?:[eE][+-]?{$integer})?";
    $based = '0[xX](?:_?[0-9A-Fa-f])+|0[oO](?:_?[0-7])+|0[bB](?:_?[01])+';
    $single = "'(?:[^']|'')*'";
    $escaped = "'(?:[^'\\\\]|\\\\[\\s\\S]|'')*'";
    $double = '"(?:[^"]|"")*"';
    $backtick = '`(?:[^`]|``)*`';
    $dollar = '(\$(?:[A-Za-z_\x80-\xFF][A-Za-z_0-9\x80-\xFF]*)?\$)(?:(?!\1)[\s\S])*\1';
    if ($dialect === 'MySql') {
        return [
            'IDENT' => '[A-Za-z_0-9$\x80-\xFF]+', 'IDENT_QUOTED' => "{$backtick}|{$double}",
            'UDF_RETURNS_SYM' => 'RETURNS',
            'GRAMMAR_SELECTOR_EXPR' => '', 'GRAMMAR_SELECTOR_PART' => '', 'GRAMMAR_SELECTOR_GCOL' => '',
            'GRAMMAR_SELECTOR_CTE' => '', 'GRAMMAR_SELECTOR_DERIVED_EXPR' => '',
            'NUM' => '[0-9]+', 'LONG_NUM' => '[0-9]+', 'ULONGLONG_NUM' => '[0-9]+',
            'DECIMAL_NUM' => '(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)',
            'FLOAT_NUM' => '(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)[eE][+-]?[0-9]+',
            'HEX_NUM' => "0[xX][0-9A-Fa-f]+|[xX]'(?:[0-9A-Fa-f]{2})*'",
            'BIN_NUM' => "0[bB][01]+|[bB]'[01]*'",
            'TEXT_STRING' => $escaped . '|"(?:[^"\\\\]|\\\\[\s\S]|"")*"',
            'NCHAR_STRING' => '[nN]' . $escaped, 'DOLLAR_QUOTED_STRING_SYM' => $dollar,
            'PARAM_MARKER' => '\?', 'LEX_HOSTNAME' => '[A-Za-z_0-9.$\x80-\xFF]+',
            'UNDERSCORE_CHARSET' => '_[A-Za-z_0-9]+',
            'WITH_ROLLUP_SYM' => 'WITH\s+ROLLUP', 'WITH_CUBE_SYM' => 'WITH\s+CUBE',
        ];
    }
    if ($dialect === 'PostgreSql') {
        $continuation = '(?:[ \\t\\f\\v]|--[^\\n\\r])*[\\n\\r](?:[ \\t\\n\\r\\f\\v]+|--[^\\n\\r]*[\\n\\r])*';

        return [
            'IDENT' => $word . '|"(?:[^"]|"")+"', 'UIDENT' => '[uU]&"(?:[^"]|"")+"',
            'MODE_TYPE_NAME' => '', 'MODE_PLPGSQL_EXPR' => '', 'MODE_PLPGSQL_ASSIGN1' => '', 'MODE_PLPGSQL_ASSIGN2' => '', 'MODE_PLPGSQL_ASSIGN3' => '',
            'ICONST' => "{$integer}|{$based}", 'FCONST' => "{$number}|{$based}",
            'SCONST' => "{$single}(?:{$continuation}{$single})*|[eE]{$escaped}(?:{$continuation}{$escaped})*|{$dollar}",
            'USCONST' => "[uU]&{$single}(?:{$continuation}{$single})*",
            'BCONST' => "[bB]'[^']*'(?:{$continuation}'[^']*')*",
            'XCONST' => "[xX]'[^']*'(?:{$continuation}'[^']*')*",
            'PARAM' => '\$[0-9]+', 'Op' => '(?!.*(?:--|/\*))[+*/<>=\x7E!@#%^&|`?\x2D]+',
            'NOT_LA' => 'NOT', 'NULLS_LA' => 'NULLS', 'WITH_LA' => 'WITH', 'WITHOUT_LA' => 'WITHOUT', 'FORMAT_LA' => 'FORMAT',
        ];
    }

    return [
        'ID' => "{$word}|{$double}|{$backtick}|\\[[^\\]]*\\]", 'STRING' => $single,
        'INTEGER' => '[0-9]+|0[xX][0-9A-Fa-f]+',
        'FLOAT' => '(?:[0-9]+\.[0-9]*|\.[0-9]+)(?:[eE][+-]?[0-9]+)?|[0-9]+[eE][+-]?[0-9]+',
        'QNUMBER' => "(?=[^ ]*_)(?:{$number}|0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*)",
        'BLOB' => "[xX]'(?:[0-9A-Fa-f]{2})*'",
        'VARIABLE' => '\?[0-9]*|[:@$#](?=(?:::)*[A-Za-z_0-9$\x80-\xFF])(?:[A-Za-z_0-9$\x80-\xFF]|::)+(?:\([^\s)]*\))?',
        'SEMI' => ';?',
    ];
}

/**
 * Compiles finite keyword/operator domains and the grammar's terminal classes.
 *
 * @return array<string, string>
 */
function terminalPatterns(Grammar $grammar, string $version, string $dialect): array
{
    $language = ['MySql' => 'mysql', 'PostgreSql' => 'postgresql', 'Sqlite' => 'sqlite'][$dialect];
    $data = require (new VersionRegistry())->resolve($language, $version)->keywordPath;
    $domains = [];
    foreach ($data as $group) {
        foreach ($group as $word => $symbol) {
            $domains[$symbol][] = preg_quote((string) $word, '~');
        }
    }
    $operators = match ($dialect) {
        'Sqlite' => SqlParser\Sqlite\Lexer\OperatorScanner::OPERATORS,
        'PostgreSql' => ['::' => 'TYPECAST', ':=' => 'COLON_EQUALS', '=>' => 'EQUALS_GREATER', '<=' => 'LESS_EQUALS', '>=' => 'GREATER_EQUALS', '<>' => 'NOT_EQUALS', '!=' => 'NOT_EQUALS'],
        default => ['!=' => 'NE', '<>' => 'NE', '!' => 'NOT2_SYM', '||' => 'OR2_SYM', ':=' => 'SET_VAR', '->' => 'JSON_SEPARATOR_SYM', '->>' => 'JSON_UNQUOTED_SEPARATOR_SYM'],
    };
    foreach ($operators as $word => $symbol) {
        $domains[$symbol][] = preg_quote($word, '~');
    }
    $patterns = lexicalPatterns($dialect);
    foreach ($domains as $symbol => $choices) {
        $patterns[$symbol] ??= implode('|', array_unique($choices));
    }
    foreach ($grammar->tokenClasses as $symbol => $members) {
        $patterns[$grammar->symbols->name($symbol)] = implode('|', array_map(static fn (int $member): string => $patterns[$grammar->symbols->name($member)], $members));
    }
    if ($grammar->wildcard !== null) {
        $patterns[$grammar->symbols->name($grammar->wildcard)] = implode('|', array_unique(array_values($patterns)));
    }

    return $patterns;
}
