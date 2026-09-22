<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Assignment;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Assignment;
use SqlSemantics\Model\Write\Storage\Path;

/**
 * Stores the destination's declared default, without a scalar value operand.
 * @visibility public
 */
final class DefaultAssignment extends Assignment
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Path $target, Node $source)
    {
        if ($target->type()->dialect === \SqlSemantics\Dialect::Sqlite) {
            throw new InvalidStructure('SQLite assignments require a value expression.');
        }
        parent::__construct($source);
    }

    /**
     * @return array{Path}
     */
    #[Override]
    public function destinations(): array
    {
        return [$this->target];
    }
}
