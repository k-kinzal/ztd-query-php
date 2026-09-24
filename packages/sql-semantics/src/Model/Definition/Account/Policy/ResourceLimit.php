<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Policy;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One WITH resource limit; when a kind repeats, the last request wins as in MySQL.
 * @visibility public
 * @example Reading a resource limit
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE USER u WITH MAX_USER_CONNECTIONS 5');
 *     [$statement->resourceLimits[0]->kind->value, $statement->resourceLimits[0]->value] // => ['MAX_USER_CONNECTIONS', 5]
 * @example Rejecting a negative limit
 *     new \SqlSemantics\Model\Definition\Account\Policy\ResourceLimit(\SqlSemantics\Model\Definition\Account\Policy\ResourceLimitKind::UserConnections, -1); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class ResourceLimit
{
    /**
     * Requires a nonnegative integer count.
     * @throws InvalidStructure
     */
    public function __construct(public readonly ResourceLimitKind $kind, public readonly int $value)
    {
        if ($value < 0) {
            throw new InvalidStructure('A resource limit requires a nonnegative count.');
        }
    }
}
