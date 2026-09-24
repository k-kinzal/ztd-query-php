<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Role;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The number of concurrent connections a role may open; -1 removes the limit.
 * @visibility public
 * @example Reading an unlimited connection policy
 *     (new \SqlSemantics\Model\Definition\Role\ConnectionLimit(-1))->limit // => -1
 * @example Rejecting a limit below the unlimited marker
 *     new \SqlSemantics\Model\Definition\Role\ConnectionLimit(-2); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class ConnectionLimit
{
    /**
     * PostgreSQL stores the limit as a signed 32-bit integer of at least -1.
     * @throws InvalidStructure
     */
    public function __construct(public readonly int $limit)
    {
        if ($limit < -1 || $limit > 2147483647) {
            throw new InvalidStructure('A connection limit requires a 32-bit integer of at least -1.');
        }
    }
}
