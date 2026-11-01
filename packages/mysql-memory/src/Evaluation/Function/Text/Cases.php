<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Text;

use IntlChar;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Function\Strings;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * The string functions that change the case of a string: UPPER, LOWER and their synonyms UCASE and LCASE.
 *
 * Each character maps to one character. A Unicode character set maps by the simple case mapping
 * of the Unicode version of its collation: 9.0 for the _0900_ collations, 5.2 for the _520_
 * ones, and 3.0 within the Basic Multilingual Plane for the others, which also map U+03F2 to
 * U+03A3; the _turkish_ci collations map i to U+0130 and I to U+0131. A UTF-8 text is mapped in
 * place. Another character set maps the characters whose mappings it holds, latin1 only those of
 * ISO-8859-1. A binary string is unchanged.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_upper,
 * https://dev.mysql.com/doc/refman/8.4/en/charset-unicode-sets.html.
 *
 * @visibility MySqlMemory
 */
final class Cases
{
    /**
     * The Unicode character sets.
     */
    public const UNICODE = ['utf8mb4', 'utf8mb3', 'ucs2', 'utf16', 'utf16le', 'utf32'];

    /**
     * The Unicode character sets whose characters are not ASCII bytes.
     */
    public const WIDE = ['ucs2', 'utf16', 'utf16le', 'utf32'];

    /**
     * @param Strings $strings Reads the arguments as text of the result
     */
    public function __construct(public readonly Strings $strings = new Strings())
    {
    }

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('UPPER', 1, 1, $this->upper(...)),
            new Routine('UCASE', 1, 1, $this->upper(...)),
            new Routine('LOWER', 1, 1, $this->lower(...)),
            new Routine('LCASE', 1, 1, $this->lower(...)),
        ];
    }

    /**
     * UPPER: the text in upper case; a binary string is unchanged.
     *
     * @param list<Evaluable> $arguments
     */
    public function upper(Frame $frame, array $arguments, Domain $result): ?string
    {
        $texts = $this->strings->texts($frame, $arguments, $result);

        return $texts === null ? null : $this->cased($texts[0], $result->collation, true);
    }

    /**
     * LOWER: the text in lower case; a binary string is unchanged.
     *
     * @param list<Evaluable> $arguments
     */
    public function lower(Frame $frame, array $arguments, Domain $result): ?string
    {
        $texts = $this->strings->texts($frame, $arguments, $result);

        return $texts === null ? null : $this->cased($texts[0], $result->collation, false);
    }

    /**
     * Maps each character of a text of a collation to one character in upper or lower case.
     */
    public function cased(string $text, Collation $collation, bool $upper): string
    {
        $charset = $collation->charset;
        $name = Encoding::name($charset);
        $turkish = str_ends_with($collation->name, '_turkish_ci');
        if ($name === null) {
            return $text;
        }
        if (!$turkish && !in_array($charset->name, self::WIDE, true) && preg_match('/[\x80-\xFF]/', $text) !== 1) {
            return $upper ? strtoupper($text) : strtolower($text);
        }
        $version = str_contains($collation->name, '_0900_') ? 9.0 : (str_contains($collation->name, '_520_') ? 5.2 : 3.0);
        if (!in_array($charset->name, self::UNICODE, true)) {
            return $this->throughUnicode($text, $charset, $upper, $turkish);
        }
        if (!Encoding::utf8($charset) || $turkish) {
            return $this->byCharacter($text, $charset, $name, $upper, $version, $turkish);
        }

        return $this->inPlace($text, $upper, $version);
    }

    /**
     * Maps each character of a text of a character set that is not Unicode through its code point.
     *
     * A character keeps its byte when it has no code point, or its mapping is not in the
     * character set, or, in latin1, either is beyond ISO-8859-1.
     */
    public function throughUnicode(string $text, Charset $charset, bool $upper, bool $turkish): string
    {
        $utf8 = Charset::known('utf8mb4');
        $cased = '';
        foreach (Encoding::characters($text, $charset) as $character) {
            $code = $this->point(Encoding::convert($character, $charset, $utf8), 'UTF-8');
            $mapped = $code === null ? null : $this->mapped($code, $upper, 99.0, false, $turkish);
            $back = $code === null || $mapped === null || ($charset->name === 'latin1' && max($code, $mapped) > 0xFF) ? $character : Encoding::convert(mb_chr($mapped, 'UTF-8'), $utf8, $charset);
            $cased .= $back === '?' && $mapped !== 0x3F ? $character : $back;
        }

        return $cased;
    }

    /**
     * Maps each character of a text of a Unicode character set in its encoding, one character at a time.
     *
     * @param string $name The name of the encoding of the character set
     */
    public function byCharacter(string $text, Charset $charset, string $name, bool $upper, float $version, bool $turkish): string
    {
        $cased = '';
        foreach (Encoding::characters($text, $charset) as $character) {
            $code = $this->point($character, $name);
            $cased .= $code === null ? $character : mb_chr($this->mapped($code, $upper, $version, $version < 4, $turkish), $name);
        }

        return $cased;
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
}
