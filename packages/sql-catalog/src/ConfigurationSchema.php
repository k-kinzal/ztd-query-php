<?php

declare(strict_types=1);

namespace SqlCatalog;

use stdClass;

/**
 * Validates the documented catalog settings and resolves their filesystem paths.
 *
 * @visibility root
 */
final class ConfigurationSchema
{
    private const LIST_OPTIONS = ['paths', 'extensions', 'exclude', 'namespace', 'method', 'path', 'kind', 'table', 'sink'];

    private const SCALAR_OPTIONS = ['output', 'reporter', 'root', 'fail-on', 'severity'];

    /**
     * CLI-shaped defaults, with paths based at the configuration directory.
     *
     * @return array<string, list<string>>
     * @throws InvalidConfigurationException When a setting has an unknown name or invalid value
     */
    public static function options(stdClass $data, string $directory): array
    {
        $options = [];
        foreach (get_object_vars($data) as $key => $value) {
            if ($key === 'function-models') {
                continue;
            }
            if (in_array($key, self::SCALAR_OPTIONS, true)) {
                if (!is_string($value) || $value === '') {
                    throw new InvalidConfigurationException(sprintf('Setting "%s" must be a non-empty string.', $key));
                }
                $values = [$value];
            } elseif (in_array($key, self::LIST_OPTIONS, true)) {
                if (!is_array($value) || !array_is_list($value)) {
                    throw new InvalidConfigurationException(sprintf('Setting "%s" must be a list of strings.', $key));
                }
                $values = [];
                foreach ($value as $item) {
                    if (!is_string($item) || $item === '') {
                        throw new InvalidConfigurationException(sprintf('Setting "%s" must contain non-empty strings.', $key));
                    }
                    $values[] = $item;
                }
            } else {
                throw new InvalidConfigurationException(sprintf('Unknown catalog setting "%s".', $key));
            }
            if (in_array($key, ['paths', 'output', 'root'], true)) {
                $values = array_map(static fn (string $path): string => self::absolute($path, $directory), $values);
            }
            $options[$key === 'extensions' ? 'extension' : $key] = $values;
        }
        $options['root'] ??= [$directory];

        return $options;
    }

    /**
     * Named function models must be strings identifying autoloadable callables or invokable classes.
     *
     * @return array<string, string>
     * @throws InvalidConfigurationException When the registrations are not a mapping of names to strings
     */
    public static function models(stdClass $data): array
    {
        $fields = get_object_vars($data);
        if (!array_key_exists('function-models', $fields)) {
            return [];
        }
        $models = $fields['function-models'];
        if (!$models instanceof stdClass) {
            throw new InvalidConfigurationException('Setting "function-models" must be a mapping.');
        }
        $registered = [];
        foreach (get_object_vars($models) as $name => $model) {
            if ($name === '' || !is_string($model) || $model === '') {
                throw new InvalidConfigurationException('Function model names and callables must be non-empty strings.');
            }
            $registered[$name] = $model;
        }

        return $registered;
    }

    /**
     * An absolute path stays unchanged; a relative one starts beside the configuration.
     */
    public static function absolute(string $path, string $directory): string
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[a-z]:[\\\\\/]/i', $path) === 1) {
            return $path;
        }

        return $directory . '/' . $path;
    }
}
