<?php

declare(strict_types=1);

namespace Deriver\Exception;

use RuntimeException;

/**
 * Selected file paths could not be collected as sources.
 * @visibility public
 * @example Describing an unavailable file
 *     (new \Deriver\Exception\CollectorException('Cannot read app.php'))->getMessage() // => 'Cannot read app.php'
 */
final class CollectorException extends RuntimeException
{
}
