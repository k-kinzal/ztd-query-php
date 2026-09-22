<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

/**
 * One declared array dimension, whose bound is not a runtime size guarantee.
 * @visibility public
 */
final class ArrayDimension
{
    /**

     */
    public function __construct(
        public readonly ?Numeric\NumericParameter $length = null,
    ) {
    }

}
