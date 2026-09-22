<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Conditional;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**

 * A CASE branch associates its match expression with exactly one result. @visibility public

 */
final class When
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $test, public readonly Expression $result)
    {
        if ($test->type->dialect !== $result->type->dialect) {
            throw new InvalidStructure('CASE operands must use the same SQL dialect.');
        }
    }
}
