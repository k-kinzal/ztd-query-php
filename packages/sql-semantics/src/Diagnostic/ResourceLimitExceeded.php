<?php

declare(strict_types=1);

namespace SqlSemantics\Diagnostic;

use RuntimeException;

/**
 * A configured work limit that was reached before a result was established.
 *
 * It is an operational failure. It does not mean the SQL is unsupported, and it
 * never produces a partial or unknown model.
 *
 * @visibility public
 * @example Reporting a reached limit
 *     (new \SqlSemantics\Diagnostic\ResourceLimitExceeded('The statement nests deeper than the configured limit.'))->getMessage() // => 'The statement nests deeper than the configured limit.'
 */
final class ResourceLimitExceeded extends RuntimeException
{
}
