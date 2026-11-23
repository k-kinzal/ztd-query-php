<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column;

use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The ordered value candidates for a resolved FULL join column.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class MergedColumns
{
    use Snapshot;

    /**
     * Holds the left and right value candidates, each with its own occurrence.
     */
    public function __construct(public readonly ResolvedColumn $left, public readonly ResolvedColumn $right)
    {
    }
}
