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
     * Converts an excerpt from the client's character set and applies its byte bound.
     */
    public static function prefix(string $text, Charset $client, bool $legacy): string
    {
        $converted = $client->name === 'binary'
            ? (string) preg_replace_callback('/[\x80-\xFF]/', static fn (array $match): string => sprintf('\\x%02X', ord($match[0])), $text)
            : CharsetEncoding::convert($text, $client, Charset::known('utf8mb3'));

        return $legacy ? mb_strcut($converted, 0, 80, 'UTF-8') : substr($converted, 0, 80);
    }

    /**
     * Repairs each invalid byte while retaining complete UTF-8 characters.
     *
     * @param bool $packet Whether an error packet uses the byte's Latin-1 code point instead of '?'
     */
    public static function repair(string $text, bool $packet = false): string
    {
        return (string) preg_replace_callback(
            '/[\x00-\x7F]+|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE-\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}|([\x80-\xFF])/',
            static fn (array $match): string => !isset($match[1]) ? $match[0] : ($packet ? mb_convert_encoding($match[1], 'UTF-8', 'ISO-8859-1') : '?'),
            $text,
        );
    }

    /**
     * Encodes an error message in the result character set after repairing cut bytes.
     */
    public static function packet(string $text, ?string $results = null): string
    {
        return CharsetEncoding::convert(self::repair($text, true), Charset::known('utf8mb4'), Charset::named($results ?? 'utf8mb4') ?? Charset::known('utf8mb4'));
    }
}
