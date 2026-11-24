<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation;

use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A native NULL test of a local trigger mutation field.
 *
 * @visibility public
 * @example Selecting local rows with a known field
 *     $local = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetField(1);
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetNullTest($local, false))->isNull // => false
 */
final class TriggerTargetNullTest
{
    use Snapshot;

    /**
     * Keeps the resolved position and the polarity of the NULL test.
     */
    public function __construct(public readonly TriggerTargetField $target, public readonly bool $isNull)
    {
    }
}
