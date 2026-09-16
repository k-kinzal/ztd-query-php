<?php

declare(strict_types=1);

namespace SqlParser\PostgreSql\Lexer;

use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;

/**
 * Reads PostgreSQL's numeric literals and positional parameters.
 *
 * An integer that fits in 32 bits is `ICONST` and any other number is
 * `FCONST`, whatever its base. A digit followed by a letter, and a parameter
 * followed by one, are the trailing junk the scanner rejects.
 *
 * @visibility root
 */
final class NumberScanner
{
    /**
     * Reads the number or parameter at the cursor, if one starts there.
     *
     * @param Scan $scan The tokenization in progress
     *
     * @return Lexeme|null The lexeme, or null when no number starts here
     *
     * @throws LexicalException When the literal is malformed or followed by junk
     */
    public function scan(Scan $scan): ?Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $parameter = $cursor->match('\$[0-9]+');
        if ($parameter !== null) {
            $this->rejectJunk($scan, $start);

            return $scan->lexeme('PARAM', $start);
        }
        $prefixed = $cursor->match('0[xX](?:_?[0-9A-Fa-f])+|0[oO](?:_?[0-7])+|0[bB](?:_?[01])+');
        if ($prefixed !== null) {
            $this->rejectJunk($scan, $start);

            return $scan->lexeme(self::fitsInt32($prefixed) ? 'ICONST' : 'FCONST', $start);
        }
        if ($cursor->match('0[xXoObB]_?') !== null) {
            throw LexicalException::unexpectedCharacter($cursor->source, $start);
        }
        $integer = '[0-9](?:_?[0-9])*';
        $numeric = "(?:{$integer}\\.(?!\\.)(?:{$integer})?|\\.{$integer})";
        $real = $cursor->match("(?:{$numeric}|{$integer})[eE][-+]?{$integer}");
        if ($real === null && $cursor->match("(?:{$numeric}|{$integer})[eE][-+]?") !== null) {
            throw LexicalException::unexpectedCharacter($cursor->source, $start);
        }
        $text = $real ?? $cursor->match($numeric) ?? $cursor->match($integer);
        if ($text === null) {
            return null;
        }
        $this->rejectJunk($scan, $start);
        $isInteger = $real === null && preg_match('/^[0-9]/', $text) === 1 && !str_contains($text, '.');

        return $scan->lexeme($isInteger && self::fitsInt32($text) ? 'ICONST' : 'FCONST', $start);
    }

    /**
     * Rejects an identifier character right after a literal.
     *
     * @param Scan $scan The tokenization in progress, positioned after the literal
     * @param int $start Offset the literal began at
     *
     * @throws LexicalException When junk follows
     */
    public function rejectJunk(Scan $scan, int $start): void
    {
        if (Scan::startsIdentifier($scan->cursor->peek())) {
            throw LexicalException::unexpectedCharacter($scan->cursor->source, $start);
        }
    }

    /**
     * Reports whether an integer literal, in any base, fits in a signed 32-bit integer.
     *
     * @param string $literal The literal as written, underscores included
     *
     * @return bool True when it fits
     */
    public static function fitsInt32(string $literal): bool
    {
        $literal = str_replace('_', '', $literal);
        [$digits, $limit] = match (strtolower(substr($literal, 0, 2))) {
            '0x' => [strtolower(substr($literal, 2)), '7fffffff'],
            '0o' => [substr($literal, 2), '17777777777'],
            '0b' => [substr($literal, 2), '1111111111111111111111111111111'],
            default => [$literal, '2147483647'],
        };
        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;

        return strlen($digits) < strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) <= 0);
    }
}
