<?php

declare(strict_types=1);

namespace SqlFormatter\Compact;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Canonical spellings for grammar terminals, excluding identifier and literal roles.
 *
 * @visibility SqlFormatter
 */
final class Keywords
{
    /**
     * Identifies grammar rules that consume keywords as names.
     */
    public static function identifier(string $rule): bool
    {
        return in_array($rule, [
            'ident', 'IDENT_sys', 'ident_or_text', 'label_ident', 'role_ident',
            'ColId', 'ColLabel', 'BareColLabel', 'type_function_name', 'NonReservedWord',
            'nm', 'idj',
        ], true);
    }

    /**
     * SQLite can accept a keyword terminal directly as an identifier expression.
     */
    public static function fallback(Token $token, ?Node $parent): bool
    {
        return $parent?->name === 'expr' && count($parent->children) === 1
            && !in_array($token->name, ['NULL', 'INTEGER', 'FLOAT', 'BLOB', 'STRING', 'VARIABLE', 'CTIME_KW'], true);
    }

    /**
     * Changes only known terminal aliases and keyword case.
     */
    public static function text(Token $token, bool $identifier, bool $mysql): string
    {
        if ($identifier || in_array($token->name, ['IDENT', 'IDENT_QUOTED', 'ID', 'UNDERSCORE_CHARSET'], true)) {
            return $token->text;
        }
        if (in_array($token->name, ['NE', 'NOT_EQUALS'], true)) {
            return '<>';
        }
        if ($token->name === 'EQ') {
            return '=';
        }
        if (in_array($token->name, ['WITH_ROLLUP_SYM', 'WITH_CUBE_SYM'], true) && preg_match('~/\*[+!]|/\*M!~', $token->text) !== 1) {
            return $token->name === 'WITH_ROLLUP_SYM' ? 'WITH ROLLUP' : 'WITH CUBE';
        }
        if (preg_match('/^[a-zA-Z_]+$/D', $token->text) !== 1) {
            return $token->text;
        }
        if ($mysql) {
            return match ($token->name) {
                'DISTINCT' => 'DISTINCT',
                'REGEXP' => 'RLIKE',
                'INT_SYM' => 'INT',
                'DECIMAL_SYM' => 'DEC',
                default => strtoupper($token->text),
            };
        }
        return strtoupper($token->text);
    }
}
