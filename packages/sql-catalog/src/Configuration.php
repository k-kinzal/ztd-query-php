<?php

declare(strict_types=1);

namespace SqlCatalog;

use SqlCatalog\Analysis\FunctionModel\Registry;

/**
 * Loads the function registrations explicitly requested by an analysis run.
 *
 * @visibility root
 */
final class Configuration
{
    /**
     * Applies a PHP file returning a registration callback to an independent registry.
     *
     * @throws InvalidConfigurationException When the path or the returned value is invalid
     */
    public function load(string $path, Registry $models): Registry
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidConfigurationException(sprintf('Cannot read configuration "%s".', $path));
        }
        $configure = $this->read($path);
        $configured = clone $models;
        $configure($configured);

        return $configured;
    }

    /**
     * Reads and validates a configuration without exposing analyzer state to the file.
     *
     * @return callable(Registry): void
     * @throws InvalidConfigurationException When the file does not return a callback
     */
    public function read(string $path): callable
    {
        return (static function (string $file): callable {
            $configure = require $file;
            if (!is_callable($configure)) {
                throw new InvalidConfigurationException(sprintf('Configuration "%s" must return a callable accepting the function registry.', $file));
            }

            return $configure;
        })($path);
    }
}
