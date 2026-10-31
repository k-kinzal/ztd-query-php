<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Conversion;

/**
 * Interprets a JSON_VALUE character return width without PHP integer saturation.
 *
 * Native observations distinguish the unsigned 64-bit upper half from both
 * positive signed overflow and numbers beyond the unsigned range. Accepted
 * widths use their low 32 bits. The integer prefix determines the width; the
 * original decimal spelling remains in CastTarget for rendering.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class JsonWidths
{
    /**
     * Answers the effective width, or null for a display-width condition.
     */
    public function of(string $length): ?int
    {
        $digits = ltrim(explode('.', $length)[0], '0');
        $size = strlen($digits);
        if ($size > 20 || ($size === 20 && strcmp($digits, '18446744073709551615') > 0)) {
            return null;
        }
        $upper = $size === 20 || ($size === 19 && strcmp($digits, '9223372036854775807') > 0);
        if (!$upper && (int) $digits > 4294967295) {
            return null;
        }
        $width = 0;
        foreach (str_split($digits) as $digit) {
            $width = ($width * 10 + (int) $digit) % 4294967296;
        }
        return $width;
    }
}
