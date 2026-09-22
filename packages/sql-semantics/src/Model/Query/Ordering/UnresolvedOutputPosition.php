<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Ordering;

use SqlSemantics\Type\Identity\Numeric\NumericParameter;

/**
 * An output position whose wildcard expansion needs a missing table declaration.
 * @visibility public
 */
final class UnresolvedOutputPosition
{
    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly NumericParameter $position)
    {
        if (preg_match('/^0*[1-9][0-9]*$/D', $position->spelling) !== 1) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An output position must be a positive integer.');
        }
    }
}
