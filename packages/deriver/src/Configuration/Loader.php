<?php

declare(strict_types=1);

namespace Deriver\Configuration;

use Deriver\Exception\ConfigurationException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads YAML command settings and anchors configured paths to their configuration file.
 * @visibility root
 * @visibility Deriver\Command
 */
final class Loader
{
    /**
     * Explicit files are required to exist; null selects defaults without file discovery.
     * @throws ConfigurationException If the file cannot be read or contains invalid settings
     */
    public function load(?string $path = null): Configuration
    {
        if ($path === null) {
            return Configuration::fromArray([]);
        }
        if (str_contains($path, '://') || !is_file($path) || !is_readable($path)) {
            throw new ConfigurationException('Configuration must be a readable local YAML file: ' . $path);
        }
        set_error_handler(static function (int $severity, string $message) use ($path): never {
            throw new ConfigurationException('Cannot read configuration ' . $path . ': ' . $message);
        });
        try {
            $contents = file_get_contents($path);
        } finally {
            restore_error_handler();
        }
        if ($contents === false) {
            throw new ConfigurationException('Cannot read configuration: ' . $path);
        }
        try {
            $settings = Yaml::parse($contents, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $error) {
            throw new ConfigurationException('Cannot load configuration ' . $path . ': ' . $error->getMessage(), previous: $error);
        }
        if ($settings === null) {
            $settings = [];
        }
        if (!is_array($settings)) {
            throw new ConfigurationException('Configuration must contain a YAML mapping.');
        }
        $configuration = Configuration::fromArray($settings);
        $settings['paths'] = array_map(static fn (string $root): string => preg_match('~^(?:/|[A-Za-z]:[/\\\\])~', $root) === 1 ? $root : dirname($path) . '/' . $root, $configuration->paths);
        return Configuration::fromArray($settings);
    }
}
