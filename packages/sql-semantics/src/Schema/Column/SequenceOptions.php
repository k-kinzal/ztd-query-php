<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

/**
 * Sequence attributes; omitted attributes use database defaults.
 *
 * @visibility public
 */
final class SequenceOptions
{
    /**
     * Constructs a valid declaration.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        public readonly ?\SqlSemantics\Model\Scalar\Value\Literal $start = null,
        public readonly ?\SqlSemantics\Model\Scalar\Value\Literal $increment = null,
        public readonly ?\SqlSemantics\Model\Scalar\Value\Literal $minimum = null,
        public readonly ?\SqlSemantics\Model\Scalar\Value\Literal $maximum = null,
        public readonly ?\SqlSemantics\Model\Scalar\Value\Literal $cache = null,
        public readonly ?bool $cycle = null,
    ) {
        foreach ([$start, $increment, $minimum, $maximum, $cache] as $number) {
            if ($number !== null && preg_match('/^[+-]?[0-9]+$/D', $number->text) !== 1) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('A sequence attribute must be an integer literal.');
            }
        }
    }
}
