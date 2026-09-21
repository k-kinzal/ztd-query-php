<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Validation;

use RuntimeException;

/**
 * A requested semantic construction or edit would violate a representation invariant.
 *
 * @example Invalid construction is an explicit API failure
 *     throw new \SqlSemantics\Model\Validation\InvalidStructure('Invalid output order'); // throws \SqlSemantics\Model\Validation\InvalidStructure
 *
 * @visibility public
 */
final class InvalidStructure extends RuntimeException
{
}
