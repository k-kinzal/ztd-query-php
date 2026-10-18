<?php

declare(strict_types=1);

namespace Deriver\Exception;

use RuntimeException;

/**
 * A candidate collection cannot be represented in the requested output format.
 * @visibility public
 * @example Describing a reporting failure
 *     (new \Deriver\Exception\ReportingException('Invalid UTF-8'))->getMessage() // => 'Invalid UTF-8'
 */
final class ReportingException extends RuntimeException
{
}
