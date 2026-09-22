<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Intrinsic;

/**
 * Decodes PostgreSQL's lexical character escapes for a static language operand.
 * @visibility SqlSemantics
 */
final class CharacterEscape
{
    /**
     * Converts C-style, octal, hexadecimal, and Unicode character escapes.
     */
    public static function postgres(string $text): string
    {
        return preg_replace_callback('/\\\\(x[0-9a-fA-F]{1,2}|[0-7]{1,3}|u[0-9a-fA-F]{4}|U[0-9a-fA-F]{8}|[\s\S])/', static function (array $match): string {
            $escape = $match[1];
            return match (true) {
                $escape[0] === 'u' || $escape[0] === 'U' => self::unicode((int) hexdec(substr($escape, 1))),
                $escape[0] === 'x' && strlen($escape) > 1 => chr((int) hexdec(substr($escape, 1))),
                ctype_digit($escape[0]) && $escape[0] < '8' => chr((int) octdec($escape) % 256),
                default => match ($escape) {
                    'b' => "\x08", 'f' => "\x0c", 'n' => "\n", 'r' => "\r", 't' => "\t", default => $escape,
                },
            };
        }, $text) ?? $text;
    }

    /**
     * Encodes a Unicode code point without requiring an optional PHP extension.
     */
    public static function unicode(int $point): string
    {
        return match (true) {
            $point < 0x80 => chr($point),
            $point < 0x800 => chr(0xc0 | ($point >> 6)) . chr(0x80 | ($point & 0x3f)),
            $point < 0x10000 => chr(0xe0 | ($point >> 12)) . chr(0x80 | (($point >> 6) & 0x3f)) . chr(0x80 | ($point & 0x3f)),
            default => chr(0xf0 | ($point >> 18)) . chr(0x80 | (($point >> 12) & 0x3f)) . chr(0x80 | (($point >> 6) & 0x3f)) . chr(0x80 | ($point & 0x3f)),
        };
    }
}
