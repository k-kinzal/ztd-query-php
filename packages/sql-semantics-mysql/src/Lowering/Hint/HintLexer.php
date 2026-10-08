<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Hint;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintFailure;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\StrategyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;

/**
 * Splits the text of one hint comment into tokens, as the hint lexer of the server does.
 *
 * Rule: MYSQL-OPTIMIZER-HINTS-002. Hint names and strategy names are
 * keywords, matched without regard to case; a release knows the hints of
 * HintName::available(). Names are unquoted words of letters, digits, `_`,
 * `$` and bytes from 0x80 that are not all digits, or quoted between
 * backticks (or double quotes under ANSI_QUOTES); a quoted name is not
 * empty. Numbers are decimal digits, with an optional K, M or G size
 * suffix; a decimal is `1.5` or `.5`; a string is non-empty, between single
 * quotes (or double quotes without ANSI_QUOTES), with doubled quotes and no
 * backslash escapes. The symbols are `(`, `)`, `,`, `=` and `@` (verified
 * on live 5.7.44 and 8.4 servers). Terminates: every token consumes input.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Hint
 */
final class HintLexer
{
    /**
     * The position in the text the next token is read from.
     */
    public int $at = 0;

    /**
     * @param string $text The text between `/*+` and the end of the comment
     * @param int $base The offset of the text in the statement
     * @param GrammarRelease $release The release whose hints are read
     * @param bool $ansiQuotes Whether double quotes enclose names (ANSI_QUOTES)
     */
    public function __construct(public readonly string $text, public readonly int $base, public readonly GrammarRelease $release, public readonly bool $ansiQuotes)
    {
    }

    /**
     * Answers the next token after whitespace without taking it: its kind, value, start and end.
     *
     * @return array{string, string, int, int}
     * @throws HintRefusal When the text there is no token
     */
    public function token(): array
    {
        $at = $this->at + strspn($this->text, " \t\n\r\f\v", $this->at);

        return $this->scan($at, true);
    }

    /**
     * Answers the token that starts at a position: end, keyword, name, integer, decimal, text or symbol.
     *
     * A word is a keyword when it names a hint or a strategy the release knows and keywords are
     * read there; the original spelling of a name is kept, a keyword is upper case.
     *
     * @return array{string, string, int, int}
     * @throws HintRefusal When the text there is no token
     */
    public function scan(int $at, bool $keywords): array
    {
        $text = $this->text;
        $char = $text[$at] ?? '';
        if ($char === '') {
            return ['end', '', $at, $at];
        }
        $word = 0;
        while (self::word($text[$at + $word] ?? '')) {
            $word++;
        }
        if ($word > 0) {
            $value = substr($text, $at, $word);
            if (ctype_digit($value)) {
                return $this->number($at, $value);
            }
            if (preg_match('/\A([0-9]+)([KMG])\z/', $value, $size) === 1) {
                return ['integer', self::scale(ltrim($size[1], '0') === '' ? '0' : ltrim($size[1], '0'), ['K' => 1, 'M' => 2, 'G' => 3][$size[2]]), $at, $at + $word];
            }
            $upper = strtoupper($value);

            return $keywords && $this->keyword($upper) ? ['keyword', $upper, $at, $at + $word] : ['name', $value, $at, $at + $word];
        }
        if ($char === '.' && ctype_digit($text[$at + 1] ?? '')) {
            $digits = strspn($text, '0123456789', $at + 1);

            return ['decimal', substr($text, $at, $digits + 1), $at, $at + 1 + $digits];
        }
        if ($char === '`' || $char === "'" || $char === '"') {
            return $this->quoted($at, $char);
        }
        if (str_contains('(),=@', $char)) {
            return ['symbol', $char, $at, $at + 1];
        }

        throw $this->refuse($at);
    }

    /**
     * Tells whether a byte is part of an unquoted word: a letter, a digit, `_`, `$` or a byte from 0x80.
     */
    public static function word(string $byte): bool
    {
        return $byte !== '' && (ctype_alnum($byte) || $byte === '_' || $byte === '$' || ord($byte) >= 0x80);
    }

    /**
     * Answers the number that starts at a position: an integer, or a decimal when a fraction follows.
     *
     * @return array{string, string, int, int}
     * @throws HintRefusal When a point follows the digits without a fraction
     */
    public function number(int $at, string $digits): array
    {
        $end = $at + strlen($digits);
        if (($this->text[$end] ?? '') !== '.') {
            return ['integer', ltrim($digits, '0') === '' ? '0' : ltrim($digits, '0'), $at, $end];
        }
        $fraction = strspn($this->text, '0123456789', $end + 1);
        if ($fraction === 0) {
            throw $this->refuse($at);
        }

        return ['decimal', substr($this->text, $at, strlen($digits) + 1 + $fraction), $at, $end + 1 + $fraction];
    }

    /**
     * Answers the quoted name or string that starts at a position, its doubled quotes read as one.
     *
     * @return array{string, string, int, int}
     * @throws HintRefusal When the quotes are not closed or enclose nothing
     */
    public function quoted(int $at, string $quote): array
    {
        $value = '';
        $position = $at + 1;
        while (true) {
            $next = strpos($this->text, $quote, $position);
            if ($next === false) {
                throw $this->refuse($at);
            }
            $value .= substr($this->text, $position, $next - $position);
            if (($this->text[$next + 1] ?? '') !== $quote) {
                break;
            }
            $value .= $quote;
            $position = $next + 2;
        }
        if ($value === '') {
            throw $this->refuse($at);
        }

        return [$quote === '`' || ($quote === '"' && $this->ansiQuotes) ? 'name' : 'text', $value, $at, $next + 1];
    }

    /**
     * Tells whether an upper-case word is a keyword of the hint comments of the release: a hint name or a strategy.
     */
    public function keyword(string $word): bool
    {
        if (in_array($word, StrategyHint::SEMIJOIN, true) || in_array($word, StrategyHint::SUBQUERY, true)) {
            return true;
        }
        $name = HintName::tryFrom($word);

        return $name !== null && $name->available($this->release);
    }

    /**
     * Answers the refusal of the comment at a position of its text.
     */
    public function refuse(int $at, HintFailure $failure = HintFailure::Syntax): HintRefusal
    {
        return new HintRefusal(new HintError($failure, $this->base + $at, $at >= strlen($this->text)));
    }

    /**
     * Answers a number written as decimal digits multiplied by 1024 a number of times, as a size written with K, M or G is.
     *
     * @example Reading 16M
     *     \SqlSemantics\Platform\MySql\Lowering\Hint\HintLexer::scale('16', 2) // => '16777216'
     */
    public static function scale(string $digits, int $times): string
    {
        for ($step = 0; $step < $times; $step++) {
            $carry = 0;
            $product = '';
            for ($index = strlen($digits) - 1; $index >= 0; $index--) {
                $value = (int) $digits[$index] * 1024 + $carry;
                $product = ($value % 10) . $product;
                $carry = intdiv($value, 10);
            }
            $digits = ltrim(($carry > 0 ? (string) $carry : '') . $product, '0');
            $digits = $digits === '' ? '0' : $digits;
        }

        return $digits;
    }
}
