<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Shape;

use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * No output field has the looked-up name, and the shape is complete.
 *
 * @visibility SqlSemantics
 */
final class AbsentField implements FieldLookup
{
    use Snapshot;

    /**
     * @param string $name The looked-up name
     */
    public function __construct(public readonly string $name)
    {
    }
}
