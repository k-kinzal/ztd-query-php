<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Tests a watched field for NULL or NOT NULL before running the program.
 *
 * @visibility public
 * @example Running the trigger when its first new field is populated
 *     $field = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField(\SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage::New, 0);
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerCondition($field, false))->isNull // => false
 */
final class TriggerCondition
{
    use Snapshot;

    /**
     * Fixes the watched field and the chosen native null test.
     */
    public function __construct(public readonly TriggerField $field, public readonly bool $isNull)
    {
    }
}
