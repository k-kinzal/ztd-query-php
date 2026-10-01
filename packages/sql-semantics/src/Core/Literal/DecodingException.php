<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Literal;

use RuntimeException;

/**
 * The value is not a context-free literal, or its encoding is invalid.
 * @example Reading the typed value
 *     (new \SqlSemantics\Core\Literal\DecodingException('Not a literal'))->getMessage() // => 'Not a literal'
 * @visibility public
 */
final class DecodingException extends RuntimeException
{
}
