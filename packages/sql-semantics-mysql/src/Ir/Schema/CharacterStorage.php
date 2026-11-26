<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\From\TextCollation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An ordinary MySQL VARCHAR storage type with an explicit native collation.
 *
 * @visibility public
 * @example Declaring an eight-character stock binary VARCHAR
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\CharacterStorage(8))->toString() // => 'VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin'
 */
final class CharacterStorage
{
    use Snapshot;

    /**
     * Fixes the character capacity and the exact native collation.
     */
    public function __construct(
        public readonly int $length,
        public readonly TextCollation $collation = TextCollation::Utf8mb4Bin,
    ) {
        Check::input($length >= 0 && $length <= 16383, 'A utf8mb4 VARCHAR length must be between 0 and 16383 characters.');
    }

    /**
     * Emits the builtin storage type independently of inherited charset settings.
     */
    public function toString(): string
    {
        return 'VARCHAR(' . $this->length . ') CHARACTER SET utf8mb4 COLLATE ' . $this->collation->value;
    }
}
