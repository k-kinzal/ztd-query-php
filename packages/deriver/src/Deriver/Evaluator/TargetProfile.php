<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator;

use Deriver\Exception\InvalidInputException;

/**
 * PHP semantics selected independently of the host runtime.
 *
 * @visibility public
 * @example Selecting the initial semantic profile
 *     (new \Deriver\Deriver\Evaluator\TargetProfile())->id() // => 'php-8.3-64bit'
 * @example Capturing the precision directive used to convert floats to strings
 *     (new \Deriver\Deriver\Evaluator\TargetProfile(floatPrecision: 14))->id() // => 'php-8.3-64bit-precision14'
 */
final class TargetProfile
{
    /**
     * @param string $version PHP major.minor version
     * @param int $integerBits Target integer width
     * @param int|null $floatPrecision Target value of the precision directive, which converts floats to strings; null leaves those conversions unresolved
     * @throws InvalidInputException If the version, integer width, or precision is invalid
     */
    public function __construct(public readonly string $version = '8.3', public readonly int $integerBits = 64, public readonly ?int $floatPrecision = null)
    {
        if (preg_match('/\A[1-9][0-9]*\.[0-9]+\z/', $version) !== 1) {
            throw new InvalidInputException('PHP version must use major.minor notation.');
        }
        if ($integerBits !== 64 || PHP_INT_SIZE !== 8) {
            throw new InvalidInputException('Value interpretation requires 64-bit integers and a 64-bit host.');
        }
        if ($floatPrecision !== null && $floatPrecision < -1) {
            throw new InvalidInputException('The precision directive accepts -1 or a non-negative integer.');
        }
    }

    /**
     * Names the semantic target in manifests and explanations.
     * @return string The profile identifier
     */
    public function id(): string
    {
        return 'php-' . $this->version . '-' . $this->integerBits . 'bit' . ($this->floatPrecision === null ? '' : '-precision' . $this->floatPrecision);
    }
}
