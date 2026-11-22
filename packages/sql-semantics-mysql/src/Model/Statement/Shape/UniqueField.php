<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Shape;

use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * Exactly one output field has the looked-up name.
 *
 * @visibility SqlSemantics
 */
final class UniqueField implements FieldLookup
{
    use Snapshot;

    /**
     * @param Field $field The field
     */
    public function __construct(public readonly Field $field)
    {
    }
}
