<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Identifier;

use SqlSemantics\Platform\MySql\Model\Statement\Identifier\CaseTable\CyrillicArmenianCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\CaseTable\ExtendedBmpCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\CaseTable\LatinGreekCase;

/**
 * The native BMP case conversion used by MySQL's system identifier comparison.
 *
 * The nonidentity pairs are measured against the fixed utf8 general case table.
 *
 * @visibility SqlSemantics
 */
final class NativeIdentifierCase
{
    private const LOWER = [
        ...LatinGreekCase::LOWER,
        ...CyrillicArmenianCase::LOWER,
        ...ExtendedBmpCase::LOWER,
    ];

    /**
     * Maps a decoded native identifier to its exact case-comparison key.
     */
    public function fold(string $name): string
    {
        return strtr($name, self::LOWER);
    }
}
