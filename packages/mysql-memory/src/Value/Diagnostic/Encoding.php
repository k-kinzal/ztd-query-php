<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Diagnostic;

use MySqlMemory\Value\Encoding as CharsetEncoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * Converts diagnostic source text, stored conditions and protocol error messages.
 *
 * Syntax excerpts use the system's three-byte UTF-8 representation before the
 * 80-byte bound. Legacy releases keep complete characters. Modern releases
 * can retain incomplete bytes: conditions replace them with question marks,
 * while error packets transcode each such byte from ISO-8859-1. Established
 * through SQL observations on 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class Encoding
{
    /**
     * Converts a NUL-terminated excerpt from the client's character set and applies its byte bound.
     */
    public static function prefix(string $text, Charset $client, bool $legacy): string
    {
        $text = explode("\0", $text, 2)[0];
        $converted = $client->name === 'binary'
            ? (string) preg_replace_callback('/[\x80-\xFF]/', static fn (array $match): string => sprintf('\\x%02X', ord($match[0])), $text)
            : CharsetEncoding::convert($text, $client, Charset::known('utf8mb3'));

        return $legacy ? mb_strcut($converted, 0, 80, 'UTF-8') : substr($converted, 0, 80);
    }

    /**
     * Repairs each invalid byte while retaining complete UTF-8 characters.
     *
     * @param bool $packet Whether an error packet uses the byte's Latin-1 code point instead of '?'
     * @param bool $threeByte Whether the source is the diagnostic system charset, which excludes four-byte characters
     */
    public static function repair(string $text, bool $packet = false, bool $threeByte = false): string
    {
        $supplementary = $threeByte ? '' : '|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}';

        return (string) preg_replace_callback(
            '/[\x00-\x7F]+|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE-\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]' . $supplementary . '|([\x80-\xFF])/',
            static fn (array $match): string => !isset($match[1]) ? $match[0] : ($packet ? mb_convert_encoding($match[1], 'UTF-8', 'ISO-8859-1') : '?'),
            $text,
        );
    }

    /**
     * Encodes a system error message, escaping code points absent from the result charset.
     */
    public static function packet(string $text, ?string $results = null): string
    {
        $target = Charset::named($results ?? 'utf8mb4') ?? Charset::known('utf8mb4');
        if ($target->name === 'binary') {
            return $text;
        }
        $source = Charset::known('utf8mb4');
        $encoded = '';
        foreach (mb_str_split(self::repair($text, true, true), 1, 'UTF-8') as $character) {
            $converted = CharsetEncoding::convert($character, $source, $target);
            $encoded .= $converted === '?' && $character !== '?' ? sprintf('\\%04X', mb_ord($character, 'UTF-8')) : $converted;
        }

        return $encoded;
    }

    /**
     * Encodes a stored condition using replacement characters; binary results retain its bytes.
     */
    public static function condition(string $text, ?string $results = null): string
    {
        $target = Charset::named($results ?? 'utf8mb3') ?? Charset::known('utf8mb3');

        return $target->name === 'binary' ? $text : CharsetEncoding::convert(self::repair($text, false, true), Charset::known('utf8mb3'), $target);
    }
}
