<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * German DIN-2 primary weights over the public Unicode 4.0 collation data.
 *
 * Native SQL comparisons over every valid BMP code point distinguish eight
 * characters from the untailored weights. Combining sequences are not normalized:
 * a followed by a combining diaeresis retains the weight of a, while ä expands to ae.
 * The latin1 collation uses a separate single-byte mapping.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-unicode-sets.html.
 *
 * @visibility MySqlMemory
 */
final class GermanWeights
{
    /**
     * Selects Unicode german2 collations, excluding latin1_german2_ci.
     */
    public function supports(Collation $collation): bool
    {
        return $collation->charset->maxLength > 1 && str_ends_with($collation->name, '_german2_ci');
    }

    /**
     * Answers a code point's primary weights, retaining untailored implicit ranges.
     */
    public function primary(int $point): string
    {
        return match ($point) {
            0xC4, 0xC6, 0xE4, 0xE6 => "\x0E\x33\x0E\x8B",
            0xD6, 0xF6 => "\x0F\x82\x0E\x8B",
            0xDC, 0xFC => "\x10\x1F\x0E\x8B",
            default => (new LegacyUnicodeWeights(false))->primary($point),
        };
    }
}
