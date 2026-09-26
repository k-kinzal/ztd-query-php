<?php

declare(strict_types=1);

namespace SqlFixture\Syntax;

/**
 * Writes a Unicode code point as the UTF-8 bytes that spell it.
 *
 * The dialects let an escape in a string or an identifier name a code point
 * rather than write its bytes, and reading such an escape means writing those
 * bytes back.
 *
 * @visibility root
 */
final class CodePoint
{
    /**
     * Answers the UTF-8 bytes of one code point.
     */
    public function utf8(int $code): string
    {
        return match (true) {
            $code < 0x80 => chr($code),
            $code < 0x800 => chr(0xC0 | $code >> 6) . chr(0x80 | $code & 0x3F),
            $code < 0x10000 => chr(0xE0 | $code >> 12) . chr(0x80 | ($code >> 6) & 0x3F) . chr(0x80 | $code & 0x3F),
            default => chr(0xF0 | $code >> 18) . chr(0x80 | ($code >> 12) & 0x3F) . chr(0x80 | ($code >> 6) & 0x3F) . chr(0x80 | $code & 0x3F),
        };
    }
}
