<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Coverage;

use RuntimeException;

/**
 * Reports a measurement infrastructure failure independently of SQL generation.
 *
 * @visibility public
 * @example Distinguish measurement failure from generated SQL failures
 *     $failure = new \SqlFaker\Generation\Coverage\CoverageException('snapshot unavailable');
 *     $failure->getMessage() // => 'snapshot unavailable'
 */
final class CoverageException extends RuntimeException
{
}
