<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

use InvalidArgumentException;

/**
 * Rejects invalid lexical, schema, or expression definitions at their public boundary.
 *
 * Extends the existing invalid-argument contract so callers can continue catching
 * malformed configuration with InvalidArgumentException.
 */
final class InvalidDefinitionException extends InvalidArgumentException
{
}
