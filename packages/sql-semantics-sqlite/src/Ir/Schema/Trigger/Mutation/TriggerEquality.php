<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * An ordered equality between a destination field and an active trigger row image.
 *
 * @visibility public
 * @example Keeping the row-image operand first for native collation selection
 *     $local = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetField(1);
 *     $image = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField(\SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage::New, 0);
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerEquality($local, $image, false))->targetFirst // => false
 */
final class TriggerEquality
{
    use Snapshot;

    /**
     * Keeps both bound operands and the orientation of the comparison.
     */
    public function __construct(
        public readonly TriggerTargetField $target,
        public readonly TriggerField $image,
        public readonly bool $targetFirst,
    ) {
    }
}
