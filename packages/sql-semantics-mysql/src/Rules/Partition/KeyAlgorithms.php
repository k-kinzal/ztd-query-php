<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Partition;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

/**
 * The KEY partition hash algorithms, including decimal and hexadecimal spellings.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-key.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class KeyAlgorithms
{
    /**
     * Permits only the values one and two, with optional leading zeros.
     */
    public function accepts(Numeral $number): bool
    {
        return in_array(ltrim($number->text, '0'), ['1', '2'], true);
    }
}
