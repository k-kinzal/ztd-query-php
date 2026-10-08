<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use IntlChar;
use MySqlMemory\Error\ErrorCode;
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
 * The string functions that build strings: CONCAT, LEFT, SUBSTRING, REPLACE, LPAD and the others.
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
            new Routine('LTRIM', 1, 1, fn (Frame $f, array $a, Domain $r): ?string => $this->strip($f, $a, $r, true, false)),
            new Routine('RTRIM', 1, 1, fn (Frame $f, array $a, Domain $r): ?string => $this->strip($f, $a, $r, false, true)),
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
        $frame->context->warning(ErrorCode::AllowedPacketOverflowed, $function, $limit);

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
     * UPPER: the text in upper case; a binary string is unchanged.
     *
     * @param list<Evaluable> $arguments
     */
    public function upper(Frame $frame, array $arguments, Domain $result): ?string
    {
        $texts = $this->texts($frame, $arguments, $result);

        return $texts === null ? null : $this->cased($texts[0], $result->collation, true);
    }

    /**
     * LOWER: the text in lower case; a binary string is unchanged.
     *
     * @param list<Evaluable> $arguments
     */
    public function lower(Frame $frame, array $arguments, Domain $result): ?string
    {
        $texts = $this->texts($frame, $arguments, $result);

        return $texts === null ? null : $this->cased($texts[0], $result->collation, false);
    }

    /**
     * Maps each character of a text of a collation to one character in upper or lower case.
     *
     * A Unicode character set maps by the simple case mapping of the Unicode version of its
     * collation: 9.0 for the _0900_ collations, 5.2 for the _520_ ones, and 3.0 within the Basic
     * Multilingual Plane for the others, which also map U+03F2 to U+03A3; the _turkish_ci
     * collations map i to U+0130 and I to U+0131. A UTF-8 text is mapped in place. Another
     * character set maps the characters whose mappings it holds, latin1 only those of ISO-8859-1.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_upper,
     * https://dev.mysql.com/doc/refman/8.4/en/charset-unicode-sets.html.
     */
    public function cased(string $text, Collation $collation, bool $upper): string
    {
        $charset = $collation->charset;
        $name = Encoding::name($charset);
        $turkish = str_ends_with($collation->name, '_turkish_ci');
        if ($name === null) {
            return $text;
        }
        if (!$turkish && !in_array($charset->name, ['ucs2', 'utf16', 'utf16le', 'utf32'], true) && preg_match('/[\x80-\xFF]/', $text) !== 1) {
            return $upper ? strtoupper($text) : strtolower($text);
        }
        $version = str_contains($collation->name, '_0900_') ? 9.0 : (str_contains($collation->name, '_520_') ? 5.2 : 3.0);
        $utf8 = Charset::known('utf8mb4');
        if (!in_array($charset->name, ['utf8mb4', 'utf8mb3', 'ucs2', 'utf16', 'utf16le', 'utf32'], true)) {
            $cased = '';
            foreach (Encoding::characters($text, $charset) as $character) {
                $code = $this->point(Encoding::convert($character, $charset, $utf8), 'UTF-8');
                $mapped = $code === null ? null : $this->mapped($code, $upper, 99.0, false, $turkish);
                $back = $code === null || $mapped === null || ($charset->name === 'latin1' && max($code, $mapped) > 0xFF) ? $character : Encoding::convert(mb_chr($mapped, 'UTF-8'), $utf8, $charset);
                $cased .= $back === '?' && $mapped !== 0x3F ? $character : $back;
            }

            return $cased;
        }
        if (!Encoding::utf8($charset) || $turkish) {
            $cased = '';
            foreach (Encoding::characters($text, $charset) as $character) {
                $code = $this->point($character, $name);
                $cased .= $code === null ? $character : mb_chr($this->mapped($code, $upper, $version, $version < 4, $turkish), $name);
            }

            return $cased;
        }

        return $this->inPlace($text, $upper, $version);
    }

    /**
     * Maps the characters of a UTF-8 text in place, as the server maps a text whose case mapping
     * may take more bytes than the text: a longer character overwrites the bytes after it, and
     * the mapping stops at bytes that are no character or at a character that would pass the end.
     */
    public function inPlace(string $text, bool $upper, float $version): string
    {
        $end = strlen($text);
        $cased = '';
        for ($source = 0; $source < $end;) {
            $lead = ord($source < strlen($cased) ? $cased[$source] : $text[$source]);
            $width = $lead < 0x80 ? 1 : ($lead >= 0xC2 && $lead <= 0xDF ? 2 : ($lead >= 0xE0 && $lead <= 0xEF ? 3 : ($lead >= 0xF0 && $lead <= 0xF4 ? 4 : 0)));
            $bytes = '';
            for ($i = $source, $last = min($end, $source + $width); $i < $last; $i++) {
                $bytes .= $i < strlen($cased) ? $cased[$i] : $text[$i];
            }
            $code = strlen($bytes) === $width ? $this->point($bytes, 'UTF-8') : null;
            if ($code === null) {
                break;
            }
            $mapped = mb_chr($this->mapped($code, $upper, $version, $version < 4, false), 'UTF-8');
            if (strlen($cased) + strlen($mapped) > $end) {
                break;
            }
            $cased .= $mapped;
            $source += $width;
        }

        return $cased;
    }

    /**
     * Answers the code point of one character of an encoding, or null when the bytes are no character.
     */
    public function point(string $character, string $encoding): ?int
    {
        $code = $character === '' || !mb_check_encoding($character, $encoding) ? false : mb_ord($character, $encoding);

        return is_int($code) ? $code : null;
    }

    /**
     * Maps a code point to its upper or lower case in a version of Unicode, or answers it unchanged.
     */
    public function mapped(int $code, bool $upper, float $version, bool $plane, bool $turkish): int
    {
        if ($turkish && ($code === ($upper ? 0x69 : 0x49))) {
            return $upper ? 0x130 : 0x131;
        }
        if ($upper && $code === 0x3F2 && $version < 4) {
            return 0x3A3;
        }
        $mapped = $upper ? IntlChar::toupper($code) : IntlChar::tolower($code);
        if (!is_int($mapped) || $mapped === $code || ($plane && max($code, $mapped) > 0xFFFF)) {
            return $code;
        }
        foreach ([$code, $mapped] as $point) {
            $age = IntlChar::charAge($point);
            $major = $age[0] ?? null;
            $minor = $age[1] ?? null;
            if (!is_int($major) || !is_int($minor) || $major + $minor / 10 > $version) {
                return $code;
            }
        }

        return $mapped;
    }

    /**
     * LEFT: the leftmost characters.
     *
     * @param list<Evaluable> $arguments
     */
    public function left(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = $this->text($frame, $arguments[0], $result);
        $count = $this->number($frame, $arguments[1]);
        if ($text === null || $count === null) {
            return null;
        }

        return $count <= 0 ? '' : $this->slice($text, 0, $count, $result);
    }

    /**
     * RIGHT: the rightmost characters.
     *
     * @param list<Evaluable> $arguments
     */
    public function right(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = $this->text($frame, $arguments[0], $result);
        $count = $this->number($frame, $arguments[1]);
        if ($text === null || $count === null) {
            return null;
        }

        $length = $this->count($text, $result);

        return $count <= 0 ? '' : $this->slice($text, max(0, $length - $count), null, $result);
    }

    /**
     * SUBSTRING(text, position[, length]): characters from a position counted from 1, or from the end when negative.
     *
     * @param list<Evaluable> $arguments
     */
    public function substring(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = $this->text($frame, $arguments[0], $result);
        $position = $this->number($frame, $arguments[1]);
        $length = isset($arguments[2]) ? $this->number($frame, $arguments[2]) : PHP_INT_MAX;
        if ($text === null || $position === null || $length === null) {
            return null;
        }
        $count = $this->count($text, $result);
        if ($position === 0 || $length <= 0 || $position > $count || $position < -$count) {
            return '';
        }
        $start = $position > 0 ? $position - 1 : $count + $position;

        return $this->slice($text, $start, $length === PHP_INT_MAX ? null : $length, $result);
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

    /**
     * SUBSTRING_INDEX: the text before a number of occurrences of a delimiter, or after them from the end.
     *
     * @param list<Evaluable> $arguments
     */
    public function substringIndex(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = $this->text($frame, $arguments[0], $result);
        $delimiter = $this->text($frame, $arguments[1], $result);
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
     * A result that exceeds max_allowed_packet is NULL with a warning.
     *
     * @param list<Evaluable> $arguments
     */
    public function insert(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = $this->text($frame, $arguments[0], $result);
        $position = $this->number($frame, $arguments[1]);
        $length = $this->number($frame, $arguments[2]);
        $new = $this->text($frame, $arguments[3], $result);
        if ($text === null || $position === null || $length === null || $new === null) {
            return null;
        }
        $count = $this->count($text, $result);
        if ($position < 1 || $position > $count) {
            return $text;
        }
        $head = $this->slice($text, 0, $position - 1, $result);
        $tail = $length < 0 || $length > $count ? '' : $this->slice($text, $position - 1 + $length, null, $result);

        return $this->fits($frame, strlen($head) + strlen($new) + strlen($tail), 'insert') ? $head . $new . $tail : null;
    }
}
