<?php

declare(strict_types=1);

namespace Deriver\Project;

use Deriver\Exception\InvalidInputException;

/**
 * PHP semantics selected independently of the host runtime.
 *
 * @visibility public
 * @example Selecting the initial semantic profile
 *     (new \Deriver\Project\TargetProfile())->id() // => 'php-8.3-64bit'
 * @example Capturing the precision directive used to convert floats to strings
 *     (new \Deriver\Project\TargetProfile(floatPrecision: 14))->id() // => 'php-8.3-64bit-precision14'
 */
final class TargetProfile
{
    /**
     * @param string $version The verified PHP language profile
     * @param int $integerBits Target integer width
     * @param int|null $floatPrecision Target value of the precision directive, which converts floats to strings; null leaves those conversions unresolved
     * @throws InvalidInputException When the profile has not been verified
     */
    public function __construct(public readonly string $version = '8.3', public readonly int $integerBits = 64, public readonly ?int $floatPrecision = null)
    {
        if ($version !== '8.3' || $integerBits !== 64 || PHP_INT_SIZE !== 8) {
            throw new InvalidInputException('The verified semantic profile is PHP 8.3 with 64-bit integers and requires a 64-bit host.');
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
