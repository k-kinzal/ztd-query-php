<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Key;

use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One ordered reference into the owning table's declared columns.
 *
 * @visibility public
 * @example Referring to the second declared column
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyTerm(1))->slot // => 1
 */
final class KeyTerm
{
    use Snapshot;

    /**
     * Checks the term's local role. The owning declaration checks that the slot exists.
     * @throws InvalidConstruction When the column slot is negative
     */
    public function __construct(
        public readonly int $slot,
        public readonly bool $descending = false,
        public readonly Collation $collation = Collation::Binary,
    ) {
        Check::input($slot >= 0, 'A key term requires a nonnegative column slot.');
    }
}
