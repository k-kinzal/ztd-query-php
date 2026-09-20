<?php

declare(strict_types=1);

namespace SqlParser\MySql\Lexer;

use SqlParser\Lexer\Lexeme;

/**
 * Reads MySQL's numeric literals and the identifiers that begin with a digit.
 *
 * An integer becomes the narrowest of `NUM`, `LONG_NUM` and `ULONGLONG_NUM`
 * that holds it, and `DECIMAL_NUM` beyond that. Digits followed by letters
 * are an identifier, unless the letter is an exponent.
 *
 * @visibility root
 */
final class NumberScanner
{
    /**
     * Reads the number at the cursor, if one starts there.
     *
     * @param Scan $scan The tokenization in progress
     * @param WordScanner $words Reads the identifier a number turns out to be
     *
     * @return Lexeme|null The lexeme, or null when no digit starts here
     */
    public function scan(Scan $scan, WordScanner $words): ?Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        if ($cursor->peek() === '.' && ctype_digit($cursor->peek(1))) {
            return $this->fraction($scan, $start);
        }
        if (!ctype_digit($cursor->peek())) {
            return null;
        }
        $prefixed = $cursor->match('0[xX][0-9A-Fa-f]+') ?? $cursor->match('0[bB][01]+');
        if ($prefixed !== null && !Scan::isIdentifierByte($cursor->peek())) {
            return $scan->lexeme(strtolower($prefixed[1]) === 'x' ? 'HEX_NUM' : 'BIN_NUM', $start);
        }
        $cursor->seek($start);
        $digits = $cursor->match('[0-9]+') ?? '';
        if ($cursor->peek() === '.') {
            return $this->fraction($scan, $start);
        }
        if (Scan::isIdentifierByte($cursor->peek())) {
            $exponent = $cursor->lookingAt('[eE](?:[0-9]|[+-][0-9])') ? $cursor->match('[eE][+-]?[0-9]+') : null;
            if ($exponent !== null) {
                return $scan->lexeme('FLOAT_NUM', $start);
            }

            return $words->identifier($scan, $start);
        }

        return $scan->lexeme(self::integerTerminal($digits), $start);
    }

    /**
     * Reads the fraction and exponent of a number whose integer part is behind the cursor.
     *
     * @param Scan $scan The tokenization in progress, positioned on the decimal point
     * @param int $start Offset the number began at
     *
     * @return Lexeme A decimal or float lexeme
     */
    public function fraction(Scan $scan, int $start): Lexeme
    {
        $cursor = $scan->cursor;
        $cursor->match('\.[0-9]*');
        if ($cursor->lookingAt('[eE][+-]?[0-9]')) {
            $cursor->match('[eE][+-]?[0-9]+');

            return $scan->lexeme('FLOAT_NUM', $start);
        }

        return $scan->lexeme('DECIMAL_NUM', $start);
    }

    /**
     * Names the narrowest integer terminal that holds a run of digits.
     *
     * @param string $digits The digits as written
     *
     * @return string One of NUM, LONG_NUM, ULONGLONG_NUM and DECIMAL_NUM
     */
    public static function integerTerminal(string $digits): string
    {
        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;
        $length = strlen($digits);
        if ($length < 10 || ($length === 10 && strcmp($digits, '2147483647') <= 0)) {
            return 'NUM';
        }
        if ($length < 19 || ($length === 19 && strcmp($digits, '9223372036854775807') <= 0)) {
            return 'LONG_NUM';
        }
        if ($length < 20 || ($length === 20 && strcmp($digits, '18446744073709551615') <= 0)) {
            return 'ULONGLONG_NUM';
        }

        return 'DECIMAL_NUM';
    }
}
