<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Declaration;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The declared slots that a local schema definition protects from column removal.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ColumnRemoval
{
    use Snapshot;

    /**
     * @var array<int, true> The protected slots indexed by ordinal
     */
    public readonly array $protected;

    /**
     * Constructs a projection over one declared heading.
     *
     * @param array<array-key, int|string> $protected The checked restrictions from the declaration
     */
    public function __construct(public readonly int $width, array $protected)
    {
        Check::input($width > 0 && array_is_list($protected), 'Column removal facts require a declared heading and an ordered slot list.');
        $indexed = [];
        foreach ($protected as $slot) {
            Check::input(is_int($slot) && $slot >= 0 && $slot < $width, 'A removal restriction must identify a declared slot.');
            $indexed[$slot] = true;
        }
        $this->protected = $indexed;
    }
}
