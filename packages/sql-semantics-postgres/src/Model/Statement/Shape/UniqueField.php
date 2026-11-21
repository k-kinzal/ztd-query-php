<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Shape;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * Exactly one output field has the looked-up name.
 *
 * @visibility public
 * @example Finding the only field with a name
 *     $operation = (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('SELECT 1 AS a');
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
