<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An exact signed integer spelling, including numbers outside PHP's integer range.
 * @visibility public
 */
final class IntegerOffset
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $text)
    {
        if (preg_match('/\A[+-]?(?:[0-9](?:_?[0-9])*|0[xX](?:_?[0-9a-fA-F])+|0[oO](?:_?[0-7])+|0[bB](?:_?[01])+)\z/D', $text) !== 1) {
            throw new InvalidStructure('A cursor offset must be one signed integer constant.');
        }
    }
}
