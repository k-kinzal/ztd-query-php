<?php

declare(strict_types=1);

namespace Deriver\Deriver;

use Deriver\Exception\InvalidInputException;

/**
 * Independent settings interpreted by Deriver when it constructs each processing stage.
 * @visibility public
 * @example Selecting a PHP language version
 *     (new \Deriver\Deriver\DeriverConfig(phpVersion: '8.4'))->phpVersion // => '8.4'
 */
final class DeriverConfig
{
    /**
     * @param string $phpVersion PHP major.minor version, independent of the host
     * @param int $integerBits Target integer width
     * @param int|null $floatPrecision Target precision directive; null leaves conversions unresolved
     * @param array<string, scalar|array<array-key, mixed>|null> $environment Explicit globals keyed by PHP variable name (such as $_GET), and getenv inputs keyed by environment name (such as APP_ENV)
     * @param bool $standardEvaluators Whether built-in function evaluators are enabled
     * @param list<MicroEvaluator> $evaluators Application micro evaluators, converted during preparation
     * @throws InvalidInputException If settings are invalid
     */
    public function __construct(
        public readonly string $phpVersion = '8.3',
        public readonly int $integerBits = 64,
        public readonly ?int $floatPrecision = null,
        public readonly array $environment = [],
        public readonly bool $standardEvaluators = true,
        public readonly array $evaluators = [],
    ) {
        if (preg_match('/\A[1-9][0-9]*\.[0-9]+\z/', $phpVersion) !== 1) {
            throw new InvalidInputException('PHP version must use major.minor notation.');
        }
        if ($integerBits !== 64 || $floatPrecision !== null && $floatPrecision < -1) {
            throw new InvalidInputException('Value interpretation requires 64-bit integers and a precision of -1 or greater.');
        }
        $pending = array_values($environment);
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif (!is_scalar($value) && $value !== null) {
                throw new InvalidInputException('Environment settings must contain only native scalar values, arrays, and null.');
            }
        }
    }

    /**
     * Returns the library defaults, independently of the host PHP version.
     * @example Selecting the default PHP version
     *     \Deriver\Deriver\DeriverConfig::default()->phpVersion // => '8.3'
     */
    public static function default(): self
    {
        return new self();
    }

}
