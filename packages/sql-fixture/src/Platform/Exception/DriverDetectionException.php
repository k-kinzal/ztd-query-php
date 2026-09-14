<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Exception;

use InvalidArgumentException;

/**
 * The PDO connection did not report a driver name.
 */
final class DriverDetectionException extends InvalidArgumentException
{
    /**
     * The PDO connection did not report a driver name.
     */
    public function __construct()
    {
        parent::__construct('Unable to detect PDO driver');
    }
}
