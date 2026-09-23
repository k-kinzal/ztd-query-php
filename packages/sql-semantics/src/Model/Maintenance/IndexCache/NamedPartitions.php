<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\IndexCache;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One or more partition names in request order.
 * @visibility public
 * @example Retaining requested names
 *     (new \SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions(['first']))->names // => ['first']
 * @example Rejecting a missing partition selection
 *     new \SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class NamedPartitions
{
    /**
     * @param non-empty-list<string> $names Unqualified identifiers
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $names)
    {
        Collections::nonEmpty($names);
        Collections::strings($names);
    }
}
