<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The string functions that build strings: CONCAT, LEFT, SUBSTRING, REPLACE, LPAD and the others.
 *
 * Arguments are read as their text; the result is in the collation the string arguments
 * aggregate to, and characters are counted in its character set.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Strings
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {

        return [
            new Routine('CONCAT', 1, -1, $this->concat(...)),
            new Routine('CONCAT_WS', 2, -1, $this->concatWs(...)),
            new Routine('UPPER', 1, 1, $this->upper(...)),
            new Routine('UCASE', 1, 1, $this->upper(...)),
            new Routine('LOWER', 1, 1, $this->lower(...)),
            new Routine('LCASE', 1, 1, $this->lower(...)),
            new Routine('LEFT', 2, 2, $this->left(...)),
            new Routine('RIGHT', 2, 2, $this->right(...)),
            new Routine('SUBSTRING', 2, 3, $this->substring(...)),
            new Routine('SUBSTR', 2, 3, $this->substring(...)),
            new Routine('MID', 3, 3, $this->substring(...)),
            new Routine('REPLACE', 3, 3, $this->replace(...)),
            new Routine('REVERSE', 1, 1, $this->reverse(...)),
            new Routine('REPEAT', 2, 2, $this->repeat(...)),
            new Routine('LPAD', 3, 3, fn (Frame $f, array $a, Domain $r): ?string => $this->pad($f, $a, $r, true)),
            new Routine('RPAD', 3, 3, fn (Frame $f, array $a, Domain $r): ?string => $this->pad($f, $a, $r, false)),
            new Routine('LTRIM', 1, 1, fn (Frame $f, array $a, Domain $r): ?string => $this->strip($f, $a, true, false)),
            new Routine('RTRIM', 1, 1, fn (Frame $f, array $a, Domain $r): ?string => $this->strip($f, $a, false, true)),
            new Routine('SPACE', 1, 1, $this->space(...)),
            new Routine('HEX', 1, 1, $this->hex(...)),
            new Routine('UNHEX', 1, 1, $this->unhex(...)),
            new Routine('SUBSTRING_INDEX', 3, 3, $this->substringIndex(...)),
            new Routine('INSERT', 4, 4, $this->insert(...)),
        ];
    }


    /**
     * Answers the length in characters of the text of a value of a domain.
     */
    public function length(Domain $domain): int
    {
        return match ($domain->kind) {
            Kind::Double => $domain->decimals < Domain::NOT_FIXED ? $domain->length : 22,
            Kind::Null => 0,
            default => $domain->length,
        };
    }

    /**
     * Reads the text of each argument, or answers null when one is NULL.
     *
     * @param list<Evaluable> $arguments
     * @return list<string>|null
     */
    public function texts(Frame $frame, array $arguments): ?array
    {
        $texts = [];
        foreach ($arguments as $argument) {
            $text = Convert::toText($argument->evaluate($frame), $argument->domain());
            if ($text === null) {
                return null;
            }
            $texts[] = $text;
        }

        return $texts;
    }

    /**
     * Reads an integer argument, or answers null.
     */
    public function number(Frame $frame, Evaluable $argument): ?int
    {
        return Convert::toInteger($argument->evaluate($frame), $argument->domain(), $frame->context);
    }

    /**
     * Tells whether a domain counts characters by bytes.
     */
    public function bytes(Domain $domain): bool
    {
        return $domain->collation->charset->maxLength === 1;
    }

    /**
     * Splits a text into the characters of a domain.
     *
     * @return list<string>
     */
    public function characters(string $text, Domain $domain): array
    {
        if ($text === '') {
            return [];
        }

        return $this->bytes($domain) || !mb_check_encoding($text, 'UTF-8') ? str_split($text) : mb_str_split($text, 1, 'UTF-8');
    }

    /**
     * CONCAT: the arguments joined; NULL when one is NULL.
     *
     * @param list<Evaluable> $arguments
     */
    public function concat(Frame $frame, array $arguments, Domain $result): ?string
    {
        $texts = $this->texts($frame, $arguments);

        return $texts === null ? null : implode('', $texts);
    }

    /**
     * CONCAT_WS: the arguments after the first joined by it, NULL ones skipped.
     *
     * @param list<Evaluable> $arguments
     */
    public function concatWs(Frame $frame, array $arguments, Domain $result): ?string
    {
        $separator = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if ($separator === null) {
            return null;
        }
        $parts = [];
        foreach (array_slice($arguments, 1) as $argument) {
            $text = Convert::toText($argument->evaluate($frame), $argument->domain());
            if ($text !== null) {
                $parts[] = $text;
            }
        }

        return implode($separator, $parts);
    }

    /**
     * UPPER: the text in upper case; a binary string is unchanged.
     *
     * @param list<Evaluable> $arguments
     */
    public function upper(Frame $frame, array $arguments, Domain $result): ?string
    {
        $texts = $this->texts($frame, $arguments);
        if ($texts === null) {
            return null;
        }

        return $result->collation === Collation::binary() ? $texts[0] : ($this->bytes($result) ? strtoupper($texts[0]) : mb_strtoupper($texts[0], 'UTF-8'));
    }

    /**
     * LOWER: the text in lower case; a binary string is unchanged.
     *
     * @param list<Evaluable> $arguments
     */
    public function lower(Frame $frame, array $arguments, Domain $result): ?string
    {
        $texts = $this->texts($frame, $arguments);
        if ($texts === null) {
            return null;
        }

        return $result->collation === Collation::binary() ? $texts[0] : ($this->bytes($result) ? strtolower($texts[0]) : mb_strtolower($texts[0], 'UTF-8'));
    }

    /**
     * LEFT: the leftmost characters.
     *
     * @param list<Evaluable> $arguments
     */
    public function left(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        $count = $this->number($frame, $arguments[1]);
        if ($text === null || $count === null) {
            return null;
        }

        return $count <= 0 ? '' : implode('', array_slice($this->characters($text, $result), 0, $count));
    }

    /**
     * RIGHT: the rightmost characters.
     *
     * @param list<Evaluable> $arguments
     */
    public function right(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        $count = $this->number($frame, $arguments[1]);
        if ($text === null || $count === null) {
            return null;
        }

        return $count <= 0 ? '' : implode('', array_slice($this->characters($text, $result), -$count));
    }

    /**
     * SUBSTRING(text, position[, length]): characters from a position counted from 1, or from the end when negative.
     *
     * @param list<Evaluable> $arguments
     */
    public function substring(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        $position = $this->number($frame, $arguments[1]);
        $length = isset($arguments[2]) ? $this->number($frame, $arguments[2]) : PHP_INT_MAX;
        if ($text === null || $position === null || $length === null) {
            return null;
        }
        $characters = $this->characters($text, $result);
        $count = count($characters);
        if ($position === 0 || $length <= 0 || abs($position) > $count) {
            return '';
        }
        $start = $position > 0 ? $position - 1 : $count + $position;

        return implode('', array_slice($characters, $start, $length === PHP_INT_MAX ? null : $length));
    }

    /**
     * REPLACE: every occurrence of a string replaced, compared byte by byte.
     *
     * @param list<Evaluable> $arguments
     */
    public function replace(Frame $frame, array $arguments, Domain $result): ?string
    {
        $texts = $this->texts($frame, $arguments);
        if ($texts === null) {
            return null;
        }

        return $texts[1] === '' ? $texts[0] : str_replace($texts[1], $texts[2], $texts[0]);
    }

    /**
     * REVERSE: the characters in reverse order.
     *
     * @param list<Evaluable> $arguments
     */
    public function reverse(Frame $frame, array $arguments, Domain $result): ?string
    {
        $texts = $this->texts($frame, $arguments);

        return $texts === null ? null : implode('', array_reverse($this->characters($texts[0], $result)));
    }

    /**
     * REPEAT: the text repeated a number of times.
     *
     * @param list<Evaluable> $arguments
     */
    public function repeat(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        $count = $this->number($frame, $arguments[1]);
        if ($text === null || $count === null) {
            return null;
        }

        return $count <= 0 ? '' : (strlen($text) * $count > 67108864 ? null : str_repeat($text, $count));
    }

    /**
     * LPAD and RPAD: the text padded to a length with a padding string, or cut to it.
     *
     * @param list<Evaluable> $arguments
     */
    public function pad(Frame $frame, array $arguments, Domain $result, bool $left): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        $length = $this->number($frame, $arguments[1]);
        $padding = Convert::toText($arguments[2]->evaluate($frame), $arguments[2]->domain());
        if ($text === null || $length === null || $padding === null || $length < 0) {
            return null;
        }
        $characters = $this->characters($text, $result);
        if (count($characters) >= $length) {
            return implode('', array_slice($characters, 0, $length));
        }
        $fill = $this->characters($padding, $result);
        if ($fill === []) {
            return null;
        }
        $needed = $length - count($characters);
        $pad = '';
        for ($i = 0; $i < $needed; $i++) {
            $pad .= $fill[$i % count($fill)];
        }

        return $left ? $pad . $text : $text . $pad;
    }

    /**
     * LTRIM and RTRIM: the text without leading or trailing spaces.
     *
     * @param list<Evaluable> $arguments
     */
    public function strip(Frame $frame, array $arguments, bool $leading, bool $trailing): ?string
    {
        $texts = $this->texts($frame, $arguments);
        if ($texts === null) {
            return null;
        }
        $text = $leading ? ltrim($texts[0], ' ') : $texts[0];

        return $trailing ? rtrim($text, ' ') : $text;
    }

    /**
     * SPACE: a number of spaces.
     *
     * @param list<Evaluable> $arguments
     */
    public function space(Frame $frame, array $arguments, Domain $result): ?string
    {
        $count = $this->number($frame, $arguments[0]);

        return $count === null ? null : str_repeat(' ', max(0, $count));
    }

    /**
     * HEX: the hexadecimal digits of a number, or of the bytes of a string.
     *
     * @param list<Evaluable> $arguments
     */
    public function hex(Frame $frame, array $arguments, Domain $result): ?string
    {
        $value = $arguments[0]->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $domain = $arguments[0]->domain();
        if ($domain->kind->numeric()) {
            $integer = Convert::toInteger($value, $domain, $frame->context, true);

            return strtoupper(sprintf('%X', (int) $integer));
        }

        return strtoupper(bin2hex((string) Convert::toText($value, $domain)));
    }

    /**
     * UNHEX: the bytes hexadecimal digits write; NULL for a string that is not hexadecimal.
     *
     * @param list<Evaluable> $arguments
     */
    public function unhex(Frame $frame, array $arguments, Domain $result): ?string
    {
        $texts = $this->texts($frame, $arguments);
        if ($texts === null || preg_match('/\A[0-9a-fA-F]*\z/', $texts[0]) !== 1) {
            return null;
        }
        $digits = strlen($texts[0]) % 2 === 1 ? '0' . $texts[0] : $texts[0];

        return (string) hex2bin($digits);
    }

    /**
     * SUBSTRING_INDEX: the text before a number of occurrences of a delimiter, or after them from the end.
     *
     * @param list<Evaluable> $arguments
     */
    public function substringIndex(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        $delimiter = Convert::toText($arguments[1]->evaluate($frame), $arguments[1]->domain());
        $count = $this->number($frame, $arguments[2]);
        if ($text === null || $delimiter === null || $count === null) {
            return null;
        }
        if ($delimiter === '' || $count === 0) {
            return '';
        }
        $parts = explode($delimiter, $text);

        return $count > 0 ? implode($delimiter, array_slice($parts, 0, $count)) : implode($delimiter, array_slice($parts, max(0, count($parts) + $count)));
    }

    /**
     * INSERT(text, position, length, new): the text with characters from a position replaced.
     *
     * @param list<Evaluable> $arguments
     */
    public function insert(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        $position = $this->number($frame, $arguments[1]);
        $length = $this->number($frame, $arguments[2]);
        $new = Convert::toText($arguments[3]->evaluate($frame), $arguments[3]->domain());
        if ($text === null || $position === null || $length === null || $new === null) {
            return null;
        }
        $characters = $this->characters($text, $result);
        if ($position < 1 || $position > count($characters)) {
            return $text;
        }
        array_splice($characters, $position - 1, $length < 0 ? count($characters) : $length, [$new]);

        return implode('', $characters);
    }
}
