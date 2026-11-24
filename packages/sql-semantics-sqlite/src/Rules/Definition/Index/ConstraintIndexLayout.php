<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition\Index;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The contiguous automatic indexes and optional primary storage of an ordinary SQLite table.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ConstraintIndexLayout
{
    use Snapshot;

    /**
     * Checks that a storage ordinal belongs to the table's native index sequence.
     */
    public function __construct(public readonly int $count, public readonly ?int $primaryStorage = null)
    {
        Check::input(
            $count >= 0 && ($primaryStorage === null || ($primaryStorage >= 1 && $primaryStorage <= $count)),
            'The primary storage ordinal must belong to the automatic index layout.'
        );
    }
}
