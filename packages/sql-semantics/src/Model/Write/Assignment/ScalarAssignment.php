<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Assignment;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Assignment;
use SqlSemantics\Model\Write\Storage\Path;

/**
 * Stores one expression in one writable location.
 *
 * @visibility public
 */
final class ScalarAssignment extends Assignment
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Path $target, public readonly Expression $value, Node $source)
    {
        parent::__construct($source);
        if ($target->type()->dialect !== $value->type->dialect) {
            throw new InvalidStructure('An assignment must use one dialect.');
        }
    }

    /**
     * @return non-empty-list<Path>
     */
    #[Override]
    public function destinations(): array
    {
        return [$this->target];
    }
}
