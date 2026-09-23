<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\IndexCache;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Explicit index names in request order, including an explicit empty list.
 * @visibility public
 * @example Retaining requested names
 *     (new \SqlSemantics\Model\Maintenance\IndexCache\NamedIndexes(['first']))->names // => ['first']
 */
final class NamedIndexes
{
    /**
     * @param list<string> $names Unqualified identifiers
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $names)
    {
        Collections::strings($names);
    }
}
