<?php

declare(strict_types=1);

namespace Deriver\Configuration;

use Deriver\Exception\ConfigurationException;

/**
 * Validates and retains native application inputs at the configuration boundary.
 * @visibility public
 * @example Keeping an explicit environment value
 *     (new \Deriver\Configuration\Environment(['APP_ENV' => 'test']))->values // => ['APP_ENV' => 'test']
 */
final class Environment
{
    /**
     * @var array<string, scalar|array<array-key, mixed>|null> Validated native inputs
     */
    public readonly array $values;

    /**
     * @param array<array-key, mixed> $input Untrusted settings supplied by YAML or PHP
     * @throws ConfigurationException If a name or nested value is not a native input
     */
    public function __construct(array $input)
    {
        $environment = [];
        foreach ($input as $name => $value) {
            if (!is_string($name) || $name === '') {
                throw new ConfigurationException('Environment names must be nonempty strings.');
            }
            if (!is_scalar($value) && !is_array($value) && $value !== null) {
                throw new ConfigurationException('Environment values must be scalars, arrays, or null.');
            }
            $environment[$name] = $value;
        }
        $pending = array_values($environment);
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif (!is_scalar($value) && $value !== null) {
                throw new ConfigurationException('Environment values must be scalars, arrays, or null.');
            }
        }
        $this->values = $environment;
    }
}
