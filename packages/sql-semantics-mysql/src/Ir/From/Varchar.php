<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The resolved type of a native nonbinary utf8mb4 VARCHAR input slot.
 *
 * @visibility public
 * @example A VARCHAR of 32 characters under the stock binary collation
 *     (new \SqlSemantics\Platform\MySql\Ir\From\Varchar(32))->length // => 32
 */
final class Varchar
{
    use Snapshot;

    /**
     * Validates the declared length in characters under the native character set.
     */
    public function __construct(
        public readonly int $length,
        public readonly TextCollation $collation = TextCollation::Utf8mb4Bin,
        public readonly bool $nullable = true,
    ) {
        Check::input($length >= 0 && $length <= 16383, 'A utf8mb4 VARCHAR length must be between 0 and 16383 characters.');
    }
}
