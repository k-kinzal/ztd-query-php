<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Shape;

use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Exactly one output field has the looked-up name.
 *
 * @visibility public
 * @example Finding the only field with a name
 *     $operation = (new \SqlSemantics\Platform\Sqlite\Semantics())->inspect('SELECT 1 AS a');
 *     $operation->lookupField('a')->field->position // => 0
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
