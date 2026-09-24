<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql\Target;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Large objects selected by object identifier.
 * @visibility public
 * @example Reading the identifiers
 *     (new \SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\LargeObjectTargets([12, 13]))->ids // => [12, 13]
 * @example Rejecting an identifier outside the OID range
 *     new \SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\LargeObjectTargets([-1]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class LargeObjectTargets
{
    /**
     * @param non-empty-list<int> $ids Ordered object identifiers
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $ids)
    {
        foreach (Collections::nonEmpty($ids) as $id) {
            if ($id < 0 || $id > 4294967295) {
                throw new InvalidStructure('A large object identifier requires an unsigned 32-bit integer.');
            }
        }
    }
}
