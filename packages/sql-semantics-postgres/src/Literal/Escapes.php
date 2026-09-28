<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Literal;

use SqlSemantics\Core\Literal\DecodingException;

/**
 * Decodes escape and Unicode strings, validating code points and surrogate pairs.
 * @visibility SqlSemantics
 */
final class Escapes
{
    /**
     * Decodes the validated escape representation.
     * @throws DecodingException When the representation is invalid
     */
    public function cStyle(string $body): string
    {
        $units = [];
        for ($i = 0; $i < strlen($body); ++$i) {
            if ($body[$i] !== '\\') {
                $units[] = $body[$i];
                continue;
            }
            $char = $body[++$i] ?? throw new DecodingException('Incomplete escape.');
            if (in_array($char, ['u', 'U'], true)) {
                $width = $char === 'u' ? 4 : 8;
                $units[] = $this->codepoint(substr($body, $i + 1, $width), $width);
                $i += $width;
            } elseif ($char === 'x' && preg_match('/\G[0-9a-fA-F]{1,2}/', $body, $match, 0, $i + 1) === 1) {
                $units[] = chr((int) hexdec($match[0]));
                $i += strlen($match[0]);
            } elseif (str_contains('01234567', $char)) {
                if (preg_match('/\G[0-7]{1,3}/', $body, $match, 0, $i) !== 1) {
                    throw new DecodingException('Invalid octal escape.');
                }
                $units[] = chr((int) octdec($match[0]) % 256);
                $i += strlen($match[0]) - 1;
            } else {
                $units[] = match ($char) {
                    'b' => "\x08", 'f' => "\x0c", 'n' => "\n", 'r' => "\r", 't' => "\t", default => $char
                };
            }
        }
        return $this->assemble($units);
    }

    /**
     * Decodes the validated escape representation.
     * @throws DecodingException When the representation is invalid
     */
    public function unicode(string $body, string $escape): string
    {
        if (strlen($escape) !== 1 || ctype_xdigit($escape) || ctype_space($escape) || str_contains('+\'"', $escape)) {
            throw new DecodingException('Invalid Unicode escape character.');
        }
        $units = [];
        for ($i = 0; $i < strlen($body); ++$i) {
            if ($body[$i] !== $escape) {
                $units[] = $body[$i];
                continue;
            }
            ++$i;
            if (($body[$i] ?? '') === $escape) {
                $units[] = $escape;
                continue;
            }
            $width = ($body[$i] ?? '') === '+' ? 6 : 4;
            $i += $width === 6 ? 1 : 0;
            $units[] = $this->codepoint(substr($body, $i, $width), $width);
            $i += $width - 1;
        }
        return $this->assemble($units);
    }

    /**
     * Decodes the validated escape representation.
     * @throws DecodingException When the representation is invalid
     */
    public function codepoint(string $hex, int $width): int
    {
        if (strlen($hex) !== $width || !ctype_xdigit($hex)) {
            throw new DecodingException('Invalid Unicode escape.');
        }
        $point = (int) hexdec($hex);
        if ($point === 0 || $point > 0x10ffff) {
            throw new DecodingException('Invalid Unicode code point.');
        }
        return $point;
    }

    /**
     * @param list<int|string> $units
     * @throws DecodingException When a surrogate or zero byte is invalid
     */
    public function assemble(array $units): string
    {
        $result = '';
        for ($i = 0; $i < count($units); ++$i) {
            $unit = $units[$i];
            if (is_string($unit)) {
                $result .= $unit;
                continue;
            }
            if ($unit >= 0xd800 && $unit <= 0xdbff) {
                $low = $units[++$i] ?? null;
                if (!is_int($low) || $low < 0xdc00 || $low > 0xdfff) {
                    throw new DecodingException('Unpaired Unicode surrogate.');
                }
                $unit = 0x10000 + (($unit - 0xd800) << 10) + $low - 0xdc00;
            } elseif ($unit >= 0xdc00 && $unit <= 0xdfff) {
                throw new DecodingException('Unpaired Unicode surrogate.');
            }
            $result .= $this->utf8($unit);
        }
        if (str_contains($result, "\0")) {
            throw new DecodingException('A text literal cannot contain a zero byte.');
        }
        return $result;
    }

    /**
     * Decodes the validated escape representation.
     * @throws DecodingException When the representation is invalid
     */
    public function utf8(int $point): string
    {
        if ($point <= 0 || $point > 0x10ffff || ($point >= 0xd800 && $point <= 0xdfff)) {
            throw new DecodingException('Invalid Unicode scalar value.');
        }
        return match (true) {
            $point <= 0x7f => chr($point),
            $point <= 0x7ff => chr(0xc0 | ($point >> 6)) . chr(0x80 | ($point & 0x3f)),
            $point <= 0xffff => chr(0xe0 | ($point >> 12)) . chr(0x80 | (($point >> 6) & 0x3f)) . chr(0x80 | ($point & 0x3f)),
            default => chr(0xf0 | ($point >> 18)) . chr(0x80 | (($point >> 12) & 0x3f)) . chr(0x80 | (($point >> 6) & 0x3f)) . chr(0x80 | ($point & 0x3f)),
        };
    }
}
