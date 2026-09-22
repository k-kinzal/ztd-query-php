<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation\Joining;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A same-named input pair and the merged column exposed by USING or NATURAL.
 *
 * @visibility public
 */
final class SharedColumn
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly Expression $left, public readonly Expression $right, public readonly Expression $output)
    {
        if ($name === '') {
            throw new InvalidStructure('A shared join column requires a name.');
        }
        if ($left->type->dialect !== $right->type->dialect || $left->type->dialect !== $output->type->dialect) {
            throw new InvalidStructure('A shared join column must use one SQL dialect.');
        }
    }
}
