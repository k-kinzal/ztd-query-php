<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Role;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A legacy SYSID request; PostgreSQL accepts and ignores it, so it is retained as written.
 * @visibility public
 * @example Reading the identifier
 *     (new \SqlSemantics\Model\Definition\Role\RoleSystemId(5))->id // => 5
 * @example Rejecting a negative identifier
 *     new \SqlSemantics\Model\Definition\Role\RoleSystemId(-1); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RoleSystemId
{
    /**
     * The grammar accepts only an unsigned 32-bit integer constant.
     * @throws InvalidStructure
     */
    public function __construct(public readonly int $id)
    {
        if ($id < 0 || $id > 2147483647) {
            throw new InvalidStructure('A role system identifier requires a nonnegative 32-bit integer.');
        }
    }
}
