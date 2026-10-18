<?php

declare(strict_types=1);

namespace Deriver\Configuration;

use Deriver\Exception\ConfigurationException;

/**
 * Validated command settings, independent of the preparation and evaluation layers.
 * @visibility public
 * @example Reading command defaults
 *     \Deriver\Configuration\Configuration::fromArray([])->paths // => ['.']
 */
final class Configuration
{
    /**
     * @var array<string, scalar|array<array-key, mixed>|null> Explicit native variable and environment inputs
     */
    public readonly array $environment;

    /**
     * @param list<string> $paths Search roots
     * @param list<string> $include Root-relative wildcard patterns
     * @param list<string> $exclude Root-relative wildcard patterns
     * @throws ConfigurationException If source selection is empty or a language setting is outside its supported range
     */
    private function __construct(
        public readonly array $paths,
        public readonly array $include,
        public readonly array $exclude,
        public readonly ?string $phpVersion,
        public readonly int $integerBits,
        public readonly ?int $floatPrecision,
        Environment $environment,
        public readonly bool $standardEvaluators,
        public readonly ReportFormat $format,
    ) {
        if ($paths === [] || $include === []) {
            throw new ConfigurationException('paths and include must each contain at least one entry.');
        }
        if ($phpVersion !== null && preg_match('/\A[1-9][0-9]*\.[0-9]+\z/', $phpVersion) !== 1) {
            throw new ConfigurationException('phpVersion must use major.minor notation.');
        }
        if ($integerBits !== 64 || $floatPrecision !== null && $floatPrecision < -1) {
            throw new ConfigurationException('integerBits must be 64 and floatPrecision must be null or an integer >= -1.');
        }
        $this->environment = $environment->values;
    }

    /**
     * Validates a settings mapping without reading files or constructing analysis objects.
     * @param array<array-key, mixed> $settings Settings decoded from YAML or supplied by a caller
     * @throws ConfigurationException If keys or values do not match the command schema
     */
    public static function fromArray(array $settings): self
    {
        $defaults = ['paths' => ['.'], 'include' => ['**/*.php'], 'exclude' => [], 'phpVersion' => null, 'integerBits' => 64, 'floatPrecision' => null, 'environment' => [], 'standardEvaluators' => true, 'format' => 'text'];
        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, $defaults)) {
                throw new ConfigurationException('Unknown configuration key: ' . $key);
            }
        }
        $settings = array_replace($defaults, $settings);
        $patterns = [];
        foreach (['paths', 'include', 'exclude'] as $key) {
            $items = $settings[$key];
            if (!is_array($items) || !array_is_list($items)) {
                throw new ConfigurationException($key . ' must be a list of strings.');
            }
            $patterns[$key] = [];
            foreach ($items as $item) {
                if (!is_string($item) || trim($item) === '' || str_contains($item, "\0")) {
                    throw new ConfigurationException($key . ' must contain nonempty strings without null bytes.');
                }
                $patterns[$key][] = $item;
            }
        }
        $version = $settings['phpVersion'];
        $precision = $settings['floatPrecision'];
        if ($version !== null && !is_string($version)) {
            throw new ConfigurationException('phpVersion must be a quoted major.minor string.');
        }
        if (!is_int($settings['integerBits']) || $precision !== null && !is_int($precision) || !is_bool($settings['standardEvaluators'])) {
            throw new ConfigurationException('integerBits must be 64, floatPrecision null or an integer >= -1, and standardEvaluators a boolean.');
        }
        if (!is_array($settings['environment']) || !is_string($settings['format']) || ($format = ReportFormat::tryFrom($settings['format'])) === null) {
            throw new ConfigurationException('environment must be a mapping and format must be text or json.');
        }
        $environment = new Environment($settings['environment']);
        return new self($patterns['paths'], $patterns['include'], $patterns['exclude'], $version, $settings['integerBits'], $precision, $environment, $settings['standardEvaluators'], $format);
    }
}
