<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;

/**
 * Reads an escape as ICU does: the characters \a \e \f \n \r \t \cX \xhh \x{h..} \uhhhh
 * \Uhhhhhhhh \0ooo \N{name}, the classes \d \D \s \S \w \W \h \H \v \V \R \X \p{..} \P{..}, the
 * anchors \A \z \Z \G \b \B, the back references \n and \k<name>, and \Q..\E; an escaped
 * character that is no escape stands for itself.
 *
 * A back reference is resolved once the pattern is read, so it may name a later group; its
 * number takes a further digit while it is below the groups opened so far. A code point beyond
 * Unicode or a missing digit is ER_REGEXP_BAD_ESCAPE_SEQUENCE, and a surrogate stands for U+FFFD.
 * Source: https://unicode-org.github.io/icu/userguide/strings/regexp.html; the errors were verified on a live 8.4 server.
 *
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Escapes
{
    /**
     * @param Translator $translator The translator whose pattern is read
     */
    public function __construct(public readonly Translator $translator)
    {
    }

    /**
     * Reads an escape after its backslash.
     *
     * @return list<Fragment>
     *
     * @throws SqlError When the escape is not valid
     */
    public function escape(): array
    {
        $character = $this->translator->scanner->next();
        if ($character === null) {
            throw DataError::RegexpBadEscapeSequence->error();
        }

        return match ($character) {
            'A' => [Fragment::empty('\A')],
            'z' => [Fragment::empty('\z')],
            'Z' => [Fragment::empty($this->translator->anchors->end())],
            'G' => [Fragment::empty('\G')],
            'b', 'B' => [Fragment::empty($this->translator->anchors->boundary($character === 'B', $this->translator->mode->words))],
            'd', 'D', 's', 'S', 'w', 'W', 'h', 'H', 'v', 'V' => [new Fragment($this->translator->properties->escape($character)->source())],
            'R' => [new Fragment('(?:\r\n|' . Anchors::TERMINATORS . ')', 1, 2)],
            'X' => [$this->cluster()],
            'p', 'P' => [new Fragment($this->property($character === 'P')->source())],
            'k' => [$this->reference($this->label(), true)],
            'Q' => $this->quoted(),
            '1', '2', '3', '4', '5', '6', '7', '8', '9' => [$this->reference($this->digits($character), false)],
            default => [$this->translator->literal($this->code($character) ?? $character)],
        };
    }

    /**
     * Answers the character an escape letter writes (\a \e \f \n \r \t \cX \x \u \U \0 \N), reading what follows it, or null when the letter writes none.
     *
     * @throws SqlError When the escape is not valid
     */
    public function code(string $letter): ?string
    {
        return match ($letter) {
            'a' => "\x07",
            'e' => "\x1B",
            'f' => "\f",
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'c' => ($control = $this->translator->scanner->next()) === null ? 'c' : chr(ord($control) & 0x1F),
            'x' => $this->hexadecimal(),
            'u' => $this->fixed(4),
            'U' => $this->fixed(8),
            '0' => $this->octal(),
            'N' => $this->named(),
            default => null,
        };
    }

    /**
     * Reads \x{h..} or \xhh after its x.
     *
     * @throws SqlError When no hexadecimal digit follows, or the code point is too large
     */
    public function hexadecimal(): string
    {
        $scanner = $this->translator->scanner;
        $digits = '';
        if ($scanner->peek() === '{') {
            $scanner->next();
            while (($character = $scanner->next()) !== '}') {
                if ($character === null || !ctype_xdigit($character)) {
                    throw DataError::RegexpBadEscapeSequence->error();
                }
                $digits .= $character;
            }
            if (strlen($digits) > 7) {
                throw DataError::RegexpBadEscapeSequence->error();
            }
        } else {
            while (strlen($digits) < 2 && ctype_xdigit($scanner->peek() ?? '')) {
                $digits .= $scanner->next();
            }
        }

        return $this->point($digits);
    }

    /**
     * Reads exactly a number of hexadecimal digits, as \u and \U write.
     *
     * @throws SqlError When fewer digits follow, or the code point is too large
     */
    public function fixed(int $count): string
    {
        $digits = '';
        while (strlen($digits) < $count) {
            $character = $this->translator->scanner->next();
            if ($character === null || !ctype_xdigit($character)) {
                throw DataError::RegexpBadEscapeSequence->error();
            }
            $digits .= $character;
        }

        return $this->point($digits);
    }

    /**
     * Answers the character of hexadecimal digits.
     *
     * @throws SqlError When there are no digits, or the code point is beyond Unicode
     */
    public function point(string $digits): string
    {
        $code = $digits === '' ? -1 : (int) hexdec($digits);
        if ($code < 0 || $code > 0x10FFFF) {
            throw DataError::RegexpBadEscapeSequence->error();
        }

        return $code >= 0xD800 && $code <= 0xDFFF ? "\u{FFFD}" : mb_chr($code, 'UTF-8');
    }

    /**
     * Reads the one to three octal digits after \0.
     *
     * @throws SqlError When no octal digit follows
     */
    public function octal(): string
    {
        $scanner = $this->translator->scanner;
        $digits = '';
        while (strlen($digits) < 3 && preg_match('/\A[0-7]\z/', $scanner->peek() ?? '') === 1) {
            $digits .= $scanner->next();
        }
        if ($digits === '') {
            throw DataError::RegexpBadEscapeSequence->error();
        }

        return mb_chr((int) octdec($digits), 'UTF-8');
    }

    /**
     * Reads {name} after \N, the character of a Unicode name.
     *
     * @throws SqlError When the braces are missing, or no character has the name
     */
    public function named(): string
    {
        $character = $this->translator->properties->point($this->braced());

        return $character ?? throw DataError::RegexpLibraryError->error('U_ILLEGAL_CHAR_FOUND');
    }

    /**
     * Reads the text between braces after \N, \p or \P.
     *
     * @throws SqlError When the braces are missing or empty (ER_REGEXP_ILLEGAL_ARGUMENT)
     */
    public function braced(): string
    {
        $scanner = $this->translator->scanner;
        if ($scanner->next() !== '{') {
            throw DataError::RegexpError->error();
        }
        $text = '';
        while (($character = $scanner->next()) !== '}') {
            if ($character === null) {
                throw DataError::RegexpError->error();
            }
            $text .= $character;
        }
        if (trim($text) === '') {
            throw DataError::RegexpError->error();
        }

        return $text;
    }

    /**
     * Reads {name} after \p or \P, and answers the set of the property.
     *
     * @throws SqlError When the property is unknown
     */
    public function property(bool $negated): Members
    {
        $name = $this->braced();
        $members = str_starts_with($name, '^') ? null : $this->translator->properties->members($name);
        if ($members === null) {
            throw DataError::RegexpError->error();
        }

        return $negated ? $members->complement() : $members;
    }

    /**
     * Answers \X, a grapheme cluster.
     */
    public function cluster(): Fragment
    {
        $this->translator->located = true;

        return new Fragment('\X', 1, null);
    }

    /**
     * Reads <name> after \k.
     *
     * @throws SqlError When the name is missing or not valid
     */
    public function label(): string
    {
        $scanner = $this->translator->scanner;
        if ($scanner->next() !== '<') {
            throw DataError::RegexpInvalidCaptureGroupName->error();
        }
        $name = '';
        while (($character = $scanner->next()) !== '>') {
            if ($character === null || preg_match('/\A[A-Za-z0-9]\z/', $character) !== 1) {
                throw DataError::RegexpInvalidCaptureGroupName->error();
            }
            $name .= $character;
        }

        return $name;
    }

    /**
     * Reads the number of a back reference from its first digit: a further digit belongs to it while the number is below the groups opened so far (verified on a live 8.4 server).
     */
    public function digits(string $first): string
    {
        $number = (int) $first;
        while (ctype_digit($this->translator->scanner->peek() ?? '') && $number < $this->translator->groups) {
            $number = $number * 10 + (int) $this->translator->scanner->next();
        }

        return (string) $number;
    }

    /**
     * Answers a back reference, resolved once the pattern is read.
     */
    public function reference(string $text, bool $named): Fragment
    {
        $this->translator->references[] = [$text, $named];

        return new Fragment("\0" . (count($this->translator->references) - 1) . "\0", 0, null);
    }

    /**
     * Reads \Q..\E: each character up to \E or the end stands for itself.
     *
     * @return list<Fragment>
     */
    public function quoted(): array
    {
        $scanner = $this->translator->scanner;
        $fragments = [];
        while (($character = $scanner->next()) !== null) {
            if ($character === '\\' && $scanner->peek() === 'E') {
                $scanner->next();
                break;
            }
            $fragments[] = $this->translator->literal($character);
        }

        return $fragments;
    }
}
