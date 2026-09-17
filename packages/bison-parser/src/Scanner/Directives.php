<?php

declare(strict_types=1);

namespace BisonParser\Scanner;

/**
 * The directives Bison's scanner knows, and the spellings it accepts for them.
 *
 * Bison accepts an underscore for the hyphen of a few old directives and
 * reads `%term`, `%binary` and `%defines` as `%token`, `%nonassoc` and
 * `%header`. The canonical name is the modern hyphenated one.
 *
 * @visibility root
 */
final class Directives
{
    /**
     * Canonical name by every accepted spelling, without the percent sign.
     */
    public const SPELLINGS = [
        'binary' => 'nonassoc',
        'code' => 'code',
        'debug' => 'debug',
        'default-prec' => 'default-prec',
        'default_prec' => 'default-prec',
        'define' => 'define',
        'defines' => 'header',
        'destructor' => 'destructor',
        'dprec' => 'dprec',
        'empty' => 'empty',
        'error-verbose' => 'error-verbose',
        'error_verbose' => 'error-verbose',
        'expect' => 'expect',
        'expect-rr' => 'expect-rr',
        'expect_rr' => 'expect-rr',
        'file-prefix' => 'file-prefix',
        'fixed-output-files' => 'fixed-output-files',
        'fixed_output_files' => 'fixed-output-files',
        'glr-parser' => 'glr-parser',
        'header' => 'header',
        'initial-action' => 'initial-action',
        'language' => 'language',
        'left' => 'left',
        'lex-param' => 'lex-param',
        'locations' => 'locations',
        'merge' => 'merge',
        'name-prefix' => 'name-prefix',
        'name_prefix' => 'name-prefix',
        'no-default-prec' => 'no-default-prec',
        'no_default_prec' => 'no-default-prec',
        'no-lines' => 'no-lines',
        'no_lines' => 'no-lines',
        'nonassoc' => 'nonassoc',
        'nondeterministic-parser' => 'nondeterministic-parser',
        'nterm' => 'nterm',
        'output' => 'output',
        'param' => 'param',
        'parse-param' => 'parse-param',
        'prec' => 'prec',
        'precedence' => 'precedence',
        'printer' => 'printer',
        'pure-parser' => 'pure-parser',
        'pure_parser' => 'pure-parser',
        'require' => 'require',
        'right' => 'right',
        'skeleton' => 'skeleton',
        'start' => 'start',
        'term' => 'token',
        'token' => 'token',
        'token-table' => 'token-table',
        'token_table' => 'token-table',
        'type' => 'type',
        'union' => 'union',
        'verbose' => 'verbose',
        'yacc' => 'yacc',
    ];

    /**
     * Directives that take no argument.
     */
    public const FLAGS = ['debug', 'default-prec', 'error-verbose', 'fixed-output-files', 'glr-parser', 'locations', 'no-default-prec', 'no-lines', 'nondeterministic-parser', 'pure-parser', 'token-table', 'verbose', 'yacc'];

    /**
     * Directives that take a string, and whether the string may be omitted.
     */
    public const OPTIONS = ['file-prefix' => false, 'header' => true, 'language' => false, 'name-prefix' => false, 'output' => false, 'require' => false, 'skeleton' => false];

    /**
     * Directives after which the scanner swallows an optional `=` before the argument.
     */
    public const EQUAL_OPTIONAL = ['file-prefix', 'name-prefix', 'output'];

    /**
     * Answers the canonical name of a directive as spelled.
     *
     * @param string $spelling The directive without its percent sign, as written
     *
     * @return string|null The canonical name, or null when Bison knows no such directive
     */
    public static function canonical(string $spelling): ?string
    {
        return self::SPELLINGS[$spelling] ?? null;
    }
}
