<?php

declare(strict_types=1);

namespace SqlCatalog;

use RuntimeException;
use SqlCatalog\Analysis\FunctionModel\NamedModel;
use SqlCatalog\Analysis\FunctionModel\Registry;
use stdClass;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Catalog settings read from a YAML document.
 *
 * @visibility root
 */
final class Configuration
{
    /**
     * @param array<string, list<string>> $options Validated command defaults, including source paths
     * @param array<string, string> $functionModels Function names mapped to autoloadable model callables
     * @param string|null $file The configuration's filename, or null for empty defaults
     */
    public function __construct(
        public readonly array $options = [],
        public readonly array $functionModels = [],
        public readonly ?string $file = null,
    ) {
    }

    /**
     * Reads a catalog configuration without executing it as PHP.
     *
     * @throws InvalidConfigurationException When the file or its settings are invalid
     */
    public static function load(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidConfigurationException(sprintf('Cannot read configuration "%s".', $path));
        }
        try {
            $data = Yaml::parseFile($path, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_OBJECT_FOR_MAP);
        } catch (ParseException $exception) {
            throw new InvalidConfigurationException(sprintf('Invalid configuration "%s": %s', $path, $exception->getMessage()), 0, $exception);
        }
        if ($data === null) {
            $data = new stdClass();
        }
        if (!$data instanceof stdClass) {
            throw new InvalidConfigurationException(sprintf('Configuration "%s" must contain a YAML mapping.', $path));
        }
        $absolute = realpath($path);
        $file = $absolute === false ? $path : $absolute;

        return new self(
            ConfigurationSchema::options($data, dirname($file)),
            ConfigurationSchema::models($data),
            $file,
        );
    }

    /**
     * Adds configured models to a copy of an analyzer's current registry.
     *
     * @throws RuntimeException When a configured model cannot be resolved
     */
    public function apply(Registry $models): Registry
    {
        $configured = clone $models;
        foreach ($this->functionModels as $name => $model) {
            $configured->register($name, NamedModel::resolve($model));
        }

        return $configured;
    }
}
