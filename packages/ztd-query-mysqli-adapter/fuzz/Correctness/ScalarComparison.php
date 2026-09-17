<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use JsonException;

/**
 * Compares database scalar representations using their SQL semantics.
 */
final class ScalarComparison
{
    /**
     * Compare floating-point results with absolute and relative tolerances.
     */
    public function compareFloat(float $expected, float $actual): bool
    {
        if ($expected === 0.0) {
            return abs($actual) < 0.0001;
        }
        return abs($expected - $actual) / abs($expected) < 0.001;
    }

    /**
     * Compare decimal representations after numeric normalization.
     */
    public function compareDecimal(string $expected, string $actual): bool
    {
        $expected = str_contains($expected, '.') ? rtrim(rtrim($expected, '0'), '.') : $expected;
        $actual = str_contains($actual, '.') ? rtrim(rtrim($actual, '0'), '.') : $actual;
        return $expected === $actual;
    }

    /**
     * Compare JSON documents by decoded structure.
     *
     * @throws OracleViolation When either document is invalid.
     */
    public function compareJson(string $expected, string $actual): bool
    {
        try {
            $expectedDecoded = json_decode($expected, true, 512, JSON_THROW_ON_ERROR);
            $actualDecoded = json_decode($actual, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $failure) {
            throw new OracleViolation('Invalid JSON in a compared result.', 0, $failure);
        }
        return $expectedDecoded === $actualDecoded;
    }

    /**
     * Compare SQL SET values without depending on their order.
     */
    public function compareSet(string $expected, string $actual): bool
    {
        $expectedParts = explode(',', $expected);
        $actualParts = explode(',', $actual);
        sort($expectedParts);
        sort($actualParts);
        return $expectedParts === $actualParts;
    }
}
