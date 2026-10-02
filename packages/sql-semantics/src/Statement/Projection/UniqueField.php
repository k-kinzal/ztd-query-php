<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Projection;

/**
 * A uniquely named output position, with its original field identity.
 * @visibility public
 * @example Reading a unique lookup
 *     $field = new \SqlSemantics\Statement\Projection\Field(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Identifier\Name('n'));
 *     (new \SqlSemantics\Statement\Projection\UniqueField(0, $field))->field === $field // => true
 */
final class UniqueField
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains a zero-based position without copying its field.
     */
    public function __construct(public readonly int $position, public readonly Field $field)
    {
        \SqlSemantics\Statement\Validation\Check::input($position >= 0, 'An output position cannot be negative.');
    }
}
