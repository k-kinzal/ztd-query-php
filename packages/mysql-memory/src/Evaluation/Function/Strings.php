<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use MySqlMemory\Error\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The string functions that build strings: CONCAT, REPLACE, REPEAT, LPAD and the others; and the reading of their arguments that the other string families share.
 *
 * Arguments are read as their text converted into the character set of the result, the
 * collation the string arguments aggregate to, and characters are counted in that set. A result
 * longer than max_allowed_packet is NULL with a warning (ER_WARN_ALLOWED_PACKET_OVERFLOWED).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_max_allowed_packet.
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
            new Routine('REPLACE', 3, 3, $this->replace(...)),
            new Routine('REVERSE', 1, 1, $this->reverse(...)),
            new Routine('REPEAT', 2, 2, $this->repeat(...)),
            new Routine('LPAD', 3, 3, fn (Frame $f, array $a, Domain $r): ?string => $this->pad($f, $a, $r, true)),
            new Routine('RPAD', 3, 3, fn (Frame $f, array $a, Domain $r): ?string => $this->pad($f, $a, $r, false)),
            new Routine('LTRIM', 1, 1, fn (Frame $f, array $a, Domain $r): ?string => $this->strip($f, $a, $r, true, false)),
            new Routine('RTRIM', 1, 1, fn (Frame $f, array $a, Domain $r): ?string => $this->strip($f, $a, $r, false, true)),
            new Routine('SPACE', 1, 1, $this->space(...)),
            new Routine('HEX', 1, 1, $this->hex(...)),
            new Routine('UNHEX', 1, 1, $this->unhex(...)),
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
            Kind::Integer, Kind::Decimal, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit => $domain->length,
        };
    }

    /**
     * Reads the text of each argument in the character set of the result, or answers null when one is NULL.
     *
     * @param list<Evaluable> $arguments
     * @return list<string>|null
     */
    public function texts(Frame $frame, array $arguments, Domain $result): ?array
    {
        $texts = [];
        foreach ($arguments as $argument) {
            $text = $this->text($frame, $argument, $result);
            if ($text === null) {
                return null;
            }
            $texts[] = $text;
        }

        return $texts;
    }

    /**
     * Reads the text of an argument in the character set of a string result, or answers null.
     */
    public function text(Frame $frame, Evaluable $argument, Domain $result): ?string
    {
        $domain = $argument->domain();
        $text = Convert::toText($argument->evaluate($frame), $domain);
        if ($text === null || $result->kind !== Kind::String) {
            return $text;
        }

        return Encoding::convert($text, $domain->kind === Kind::String ? $domain->collation->charset : Charset::known('utf8mb4'), $result->collation->charset);
    }

    /**
     * Answers the character set of the text of a value of a domain: that of a string, else utf8mb4.
     */
    public function charset(Domain $domain): Charset
    {
        return $domain->kind === Kind::String ? $domain->collation->charset : Charset::known('utf8mb4');
    }

    /**
     * Reads an integer argument, or answers null; an unsigned BIGINT beyond the signed range reads as the largest int.
     */
    public function number(Frame $frame, Evaluable $argument): ?int
    {
        $domain = $argument->domain();
        $value = Convert::toInteger($argument->evaluate($frame), $domain, $frame->context);

        return $value !== null && $value < 0 && $domain->kind === Kind::Integer && $domain->unsigned ? PHP_INT_MAX : $value;
    }

    /**
     * Tells whether a result of a number of bytes fits in max_allowed_packet; one that does not is NULL with a warning.
     *
     * @throws SqlError When the statement raises warnings as errors
     */
    public function fits(Frame $frame, int|float $bytes, string $function): bool
    {
        $limit = (int) $frame->context->variables->read('max_allowed_packet');
        if ($bytes <= $limit) {
            return true;
        }
        $frame->context->warning(DataError::AllowedPacketOverflowed, $function, $limit);

        return false;
    }

    /**
     * Splits a text into the characters of a domain.
     *
     * @return list<string>
     */
    public function characters(string $text, Domain $domain): array
    {
        return Encoding::characters($text, $this->charset($domain));
    }

    /**
     * Counts the characters of a text of a domain.
     */
    public function count(string $text, Domain $domain): int
    {
        return Encoding::length($text, $this->charset($domain));
    }

    /**
     * Answers the characters of a text of a domain from a position counted from 0, at most a number of them.
     */
    public function slice(string $text, int $start, ?int $length, Domain $domain): string
    {
        return Encoding::slice($text, $start, $length, $this->charset($domain));
    }

    /**
     * CONCAT: the arguments joined; NULL when one is NULL, or with a warning when the result exceeds max_allowed_packet.
     *
     * @param list<Evaluable> $arguments
     */
    public function concat(Frame $frame, array $arguments, Domain $result): ?string
    {
        $texts = $this->texts($frame, $arguments, $result);
        if ($texts === null || !$this->fits($frame, array_sum(array_map(strlen(...), $texts)), 'concat')) {
            return null;
        }

        return implode('', $texts);
    }

    /**
     * CONCAT_WS: the arguments after the first joined by it, NULL ones skipped.
     *
     * Each argument joined counts with a separator before it against max_allowed_packet, the
     * first one too; a result that exceeds it is NULL with a warning.
     *
     * @param list<Evaluable> $arguments
     */
    public function concatWs(Frame $frame, array $arguments, Domain $result): ?string
    {
        $separator = $this->text($frame, $arguments[0], $result);
        if ($separator === null) {
            return null;
        }
        $parts = [];
        $bytes = 0;
        foreach (array_slice($arguments, 1) as $argument) {
            $text = $this->text($frame, $argument, $result);
            if ($text === null) {
                continue;
            }
            if (!$this->fits($frame, $bytes + strlen($separator) + strlen($text), 'concat_ws')) {
                return null;
            }
            $bytes += ($parts === [] ? 0 : strlen($separator)) + strlen($text);
            $parts[] = $text;
        }

        return implode($separator, $parts);
    }

    /**
     * REPLACE: every occurrence of a string replaced, compared byte by byte.
     *
     * A result that exceeds max_allowed_packet is NULL with a warning.
     *
     * @param list<Evaluable> $arguments
     */
    public function replace(Frame $frame, array $arguments, Domain $result): ?string
    {
        $texts = $this->texts($frame, $arguments, $result);
        if ($texts === null) {
            return null;
        }
        if ($texts[1] === '') {
            return $texts[0];
        }
        $bytes = strlen($texts[0]) + substr_count($texts[0], $texts[1]) * (strlen($texts[2]) - strlen($texts[1]));

        return $this->fits($frame, $bytes, 'replace') ? str_replace($texts[1], $texts[2], $texts[0]) : null;
    }

    /**
     * REVERSE: the characters in reverse order.
     *
     * @param list<Evaluable> $arguments
     */
    public function reverse(Frame $frame, array $arguments, Domain $result): ?string
    {
        $texts = $this->texts($frame, $arguments, $result);

        return $texts === null ? null : implode('', array_reverse($this->characters($texts[0], $result)));
    }

    /**
     * REPEAT: the text repeated a number of times; a result that exceeds max_allowed_packet is NULL with a warning.
     *
     * @param list<Evaluable> $arguments
     */
    public function repeat(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = $this->text($frame, $arguments[0], $result);
        $count = $this->number($frame, $arguments[1]);
        if ($text === null || $count === null) {
            return null;
        }

        if ($count <= 0 || $text === '') {
            return '';
        }

        return $this->fits($frame, strlen($text) * $count, 'repeat') ? str_repeat($text, $count) : null;
    }

    /**
     * LPAD and RPAD: the text padded to a length with a padding string, or cut to it.
     *
     * A padded result counts as the length times the longest character of its character set
     * against max_allowed_packet; one that exceeds it is NULL with a warning.
     *
     * @param list<Evaluable> $arguments
     */
    public function pad(Frame $frame, array $arguments, Domain $result, bool $left): ?string
    {
        $text = $this->text($frame, $arguments[0], $result);
        $length = $this->number($frame, $arguments[1]);
        $padding = $this->text($frame, $arguments[2], $result);
        if ($text === null || $length === null || $padding === null || $length < 0) {
            return null;
        }
        $count = $this->count($text, $result);
        if ($count >= $length) {
            return $this->slice($text, 0, $length, $result);
        }
        if (!$this->fits($frame, $length * $result->collation->charset->maxLength, $left ? 'lpad' : 'rpad')) {
            return null;
        }
        $fill = $this->characters($padding, $result);
        if ($fill === []) {
            return null;
        }
        $needed = $length - $count;
        $pad = str_repeat($padding, intdiv($needed, count($fill))) . implode('', array_slice($fill, 0, $needed % count($fill)));

        return $left ? $pad . $text : $text . $pad;
    }

    /**
     * LTRIM and RTRIM: the text without leading or trailing spaces.
     *
     * @param list<Evaluable> $arguments
     */
    public function strip(Frame $frame, array $arguments, Domain $result, bool $leading, bool $trailing): ?string
    {
        $texts = $this->texts($frame, $arguments, $result);
        if ($texts === null) {
            return null;
        }
        $text = $leading ? ltrim($texts[0], ' ') : $texts[0];

        return $trailing ? rtrim($text, ' ') : $text;
    }

    /**
     * SPACE: a number of spaces; more than max_allowed_packet is NULL with a warning.
     *
     * @param list<Evaluable> $arguments
     */
    public function space(Frame $frame, array $arguments, Domain $result): ?string
    {
        $count = $this->number($frame, $arguments[0]);
        if ($count === null) {
            return null;
        }

        return $this->fits($frame, max(0, $count), 'space') ? str_repeat(' ', max(0, $count)) : null;
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
        $texts = $this->texts($frame, $arguments, $result);
        if ($texts === null || preg_match('/\A[0-9a-fA-F]*\z/', $texts[0]) !== 1) {
            return null;
        }
        $digits = strlen($texts[0]) % 2 === 1 ? '0' . $texts[0] : $texts[0];

        return (string) hex2bin($digits);
    }
}
