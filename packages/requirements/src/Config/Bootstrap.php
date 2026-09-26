<?php

declare(strict_types=1);

namespace Requirements\Config;

use Requirements\Input\InvalidInputException;

/**
 * Loads the bootstrap file a configuration names, so extension classes can be found.
 */
final class Bootstrap
{
    /**
     * Includes the bootstrap file once.
     *
     * @param string $file The bootstrap file
     *
     * @throws InvalidInputException When the file does not exist
     */
    public function load(string $file): void
    {
        if (!is_file($file)) {
            throw new InvalidInputException("Bootstrap does not exist: $file");
        }
        require_once $file;
    }
}
