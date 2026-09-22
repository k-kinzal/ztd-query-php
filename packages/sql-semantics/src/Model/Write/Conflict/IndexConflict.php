<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Conflict;

use SqlSemantics\Model\Expression;

/**
 * A conflict selected by ordered index expressions and an optional index predicate.
 * @visibility public
 */
final class IndexConflict implements Target
{
    /**
     * @var non-empty-list<Expression> Validated ordered operands
     */
    public readonly array $keys;

    /**
     * @param list<Expression> $keys
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(array $keys, public readonly ?Expression $predicate = null)
    {
        \SqlSemantics\Model\Validation\Collections::objects($keys, Expression::class);
        if ($keys === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An index selector requires its keys.');
        }
        $this->keys = \SqlSemantics\Model\Validation\Collections::nonEmpty($keys);
    }
}
