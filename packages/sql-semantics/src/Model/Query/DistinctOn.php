<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**

 * Selects one ordered row for each distinct key. @visibility public

 */
final class DistinctOn implements Quantifier
{
    /**
     * @var non-empty-list<Expression> Validated ordered operands
     */
    public readonly array $keys;

    /**
     * @param list<Expression> $keys
     * @throws InvalidStructure
     */
    public function __construct(array $keys)
    {
        Collections::objects($keys, Expression::class);
        if ($keys === []) {
            throw new InvalidStructure('DISTINCT ON requires its key expressions.');
        }
        $this->keys = Collections::nonEmpty($keys);
    }
}
