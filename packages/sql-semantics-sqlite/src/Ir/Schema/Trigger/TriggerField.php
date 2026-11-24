<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A column position in one of the watched row images.
 *
 * @visibility public
 * @example Selecting the first field of the proposed row
 *     $image = \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage::New;
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField($image, 0))->column // => 0
 */
final class TriggerField
{
    use Snapshot;

    /**
     * Keeps the exact row image and nonnegative physical position.
     * @throws InvalidConstruction When the position is negative
     */
    public function __construct(public readonly RowImage $image, public readonly int $column)
    {
        Check::input($column >= 0, 'A trigger field needs a nonnegative watched column position.');
    }
}
