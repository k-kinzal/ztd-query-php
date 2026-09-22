<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

use SqlParser\Parser\Node;

/**
 * An assignment whose concrete form determines the required input and destination shape.
 *
 * @visibility public
 */
abstract class Assignment
{
    /**
     * Records the diagnostic origin of the assignment.
     */
    public function __construct(public readonly Node $source)
    {
    }

    /**
     * @return non-empty-list<Storage\Path>
     */
    abstract public function destinations(): array;
}
