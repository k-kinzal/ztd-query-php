<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

use SqlSemantics\Schema\IndexDefinition;

/**
 * The typed definition of an index creation operation.
 *
 * @visibility public
 */
final class IndexDeclaration
{
    /**
     * Records the index's target, keys, predicate and storage properties.
     */
    public function __construct(public readonly IndexDefinition $definition)
    {
    }
}
