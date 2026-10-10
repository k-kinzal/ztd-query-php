<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Literal;

/**
 * Reads NAME_CONST's label bytes as the server's utf8mb3 metadata name.
 *
 * Leading controls, spaces, DEL and FF are discarded; the first following NUL ends the
 * name. Incomplete two- or three-byte sequences end it too. Each other invalid byte,
 * including the bytes of a supplementary character, becomes a question mark.
 * Verified through column metadata on live MySQL 8.0.44 and 8.4.7 servers.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class NameBytes
{
    /**
     * Answers the metadata spelling, independently of a literal's character-set introducer.
     */
    public static function format(string $text): string
    {
        $text = explode("\0", ltrim($text, "\x00..\x20\x7F\xFF"))[0];
        $result = '';
        $length = strlen($text);
        for ($index = 0; $index < $length;) {
            $byte = ord($text[$index]);
            $width = $byte >= 0xC0 && $byte <= 0xDF ? 2 : ($byte >= 0xE0 && $byte <= 0xEF ? 3 : 1);
            if ($index + $width > $length) {
                break;
            }
            $character = substr($text, $index, $width);
            if (mb_check_encoding($character, 'UTF-8')) {
                $result .= $character;
                $index += $width;
            } else {
                $result .= '?';
                $index++;
            }
        }

        return $result;
    }
}
