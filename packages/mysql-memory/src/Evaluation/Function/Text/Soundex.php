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

/**
 * SOUNDEX(str): the first letter of a string and the digits of the consonants after it, at least four characters; SOUNDS LIKE compares these codes.
 *
 * Characters before the first letter are skipped. The first letter is kept, an ASCII one in
 * upper case; after it only the ASCII consonants count, each digit written unless it repeats the
 * last digit written, which neither vowels nor other characters reset, and the code is not cut
 * to four characters. A letter is an ASCII letter, or in utf8mb4 and utf8mb3 any character from
 * U+00C0, in latin1 the letters of cp1252, in a binary string no other byte (verified on a live
 * 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_soundex.
 *
 * @visibility MySqlMemory
 */
final class Soundex
{
    /**
     * The digit of each ASCII consonant.
     */
    public const CODES = ['B' => 1, 'F' => 1, 'P' => 1, 'V' => 1, 'C' => 2, 'G' => 2, 'J' => 2, 'K' => 2, 'Q' => 2, 'S' => 2, 'X' => 2, 'Z' => 2, 'D' => 3, 'T' => 3, 'L' => 4, 'M' => 5, 'N' => 5, 'R' => 6];

    /**
     * The bytes above 0x7F that latin1 counts as letters.
     */
    public const LATIN1_LETTERS = "\x83\x8A\x8C\x8E\x9A\x9C\x9E\x9F";

    /**
     * @param Strings $strings Reads the argument as text of the result
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
        return [new Routine('SOUNDEX', 1, 1, $this->soundex(...))];
    }

    /**
     * SOUNDEX: the code of the text of the argument, in the character set of the result.
     *
     * @param list<Evaluable> $arguments
     */
    public function soundex(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = $this->strings->text($frame, $arguments[0], $result);

        return $text === null ? null : $this->code($text, $result->collation->charset);
    }

    /**
     * Answers the SOUNDEX code of a text of a character set, in that set.
     */
    public function code(string $text, Charset $charset): string
    {
        $utf8 = Charset::known('utf8mb4');
        $code = '';
        $last = null;
        $length = 0;
        foreach (Encoding::characters($text, $charset) as $character) {
            $unicode = $charset === Charset::binary() ? $character : Encoding::convert($character, $charset, $utf8);
            $ascii = preg_match('/\A[A-Za-z]\z/', $unicode) === 1 ? strtoupper($unicode) : null;
            if ($last === null) {
                if ($ascii === null && !$this->letter($character, $unicode, $charset)) {
                    continue;
                }
                $code = $ascii === null ? $character : Encoding::convert($ascii, $utf8, $charset);
                $last = $ascii === null ? 0 : (self::CODES[$ascii] ?? 0);
                $length = 1;
                continue;
            }
            $digit = $ascii === null ? 0 : (self::CODES[$ascii] ?? 0);
            if ($digit !== 0 && $digit !== $last) {
                $code .= Encoding::convert((string) $digit, $utf8, $charset);
                $last = $digit;
                $length++;
            }
        }

        return $last === null ? '' : $code . str_repeat(Encoding::convert('0', $utf8, $charset), max(0, 4 - $length));
    }

    /**
     * Tells whether a character that is no ASCII letter starts a code in a character set.
     */
    public function letter(string $character, string $unicode, Charset $charset): bool
    {
        if (strlen($character) === 1 && ord($character) < 0x80) {
            return false;
        }

        return match ($charset->name) {
            'binary' => false,
            'ascii' => true,
            'utf8mb4', 'utf8mb3' => ord($character[0]) >= 0xC3,
            'latin1' => ord($character) >= 0xC0 ? ord($character) !== 0xD7 && ord($character) !== 0xF7 : str_contains(self::LATIN1_LETTERS, $character),
            default => strlen($character) > 1 || (mb_check_encoding($unicode, 'UTF-8') && IntlChar::isalpha(mb_ord($unicode, 'UTF-8'))),
        };
    }
}
