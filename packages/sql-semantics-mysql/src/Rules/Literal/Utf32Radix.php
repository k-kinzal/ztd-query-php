<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Literal;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidCharacterString;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;

/**
 * Validates introduced UTF-32 hexadecimal and bit values after leading-unit padding.
 *
 * Legacy releases allow surrogate code points; modern releases reject them.
 * Quoted text has a different conversion rule and is not checked here.
 * Native observations: MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Utf32Radix
{
    /**
     * Answers the first invalid code point, with the native three-byte diagnostic prefix.
     */
    public function problem(RadixLiteral $literal, GrammarRelease $release): ?InvalidCharacterString
    {
        if ($literal->introducer?->value !== 'utf32') {
            return null;
        }
        $bytes = $literal->bytes();
        $bytes = str_repeat("\0", (4 - strlen($bytes) % 4) % 4) . $bytes;
        $legacy = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        foreach ($bytes === '' ? [] : str_split($bytes, 4) as $unit) {
            $point = ord($unit[0]) * 16777216 + ord($unit[1]) * 65536 + ord($unit[2]) * 256 + ord($unit[3]);
            if ($point > 0x10ffff || (!$legacy && $point >= 0xd800 && $point <= 0xdfff)) {
                return new InvalidCharacterString('utf32', substr($unit, 0, 3));
            }
        }

        return null;
    }
}
