<?php

declare(strict_types=1);

namespace SqlSemantics\Diagnostic;

use RuntimeException;

/**
 * A decoded name that the requested SQL output character set cannot represent.
 *
 * The semantic structure remains valid. Choose a client character set that
 * represents its names before rendering SQL for an external server. Consumers
 * of the structure itself do not need to encode identifiers as client bytes.
 *
 * @visibility public
 * @example Reporting a name that cannot be written in the requested encoding
 *     (new \SqlSemantics\Diagnostic\UnrepresentableName('A name cannot be written in latin1.'))->getMessage() // => 'A name cannot be written in latin1.'
 */
final class UnrepresentableName extends RuntimeException
{
}
