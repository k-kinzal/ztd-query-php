<?php

declare(strict_types=1);

namespace Deriver\Deriver\Parser;

use Deriver\Exception\InvalidInputException;

/**
 * The PHP language version used by a parser instance.
 * @visibility root
 * @example Selecting PHP grammar
 *     (new \Deriver\Deriver\Parser\ParserConfig('8.3'))->phpVersion // => '8.3'
 */
final class ParserConfig
{
    /**
     * @param string $phpVersion PHP version in major.minor notation
     * @throws InvalidInputException If the version does not use major.minor notation
     */
    public function __construct(public readonly string $phpVersion)
    {
        if (preg_match('/\A[1-9][0-9]*\.[0-9]+\z/', $phpVersion) !== 1) {
            throw new InvalidInputException('PHP version must use major.minor notation.');
        }
    }
}
