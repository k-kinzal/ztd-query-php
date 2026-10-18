<?php

declare(strict_types=1);

namespace Deriver\Exception;

use RuntimeException;

/**
 * Invalid or unreadable command configuration.
 * @visibility public
 * @example Describing invalid command settings
 *     (new \Deriver\Exception\ConfigurationException('Unknown key'))->getMessage() // => 'Unknown key'
 */
final class ConfigurationException extends RuntimeException
{
}
