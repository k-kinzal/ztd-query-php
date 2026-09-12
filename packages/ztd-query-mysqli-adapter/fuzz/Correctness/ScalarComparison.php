<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

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
        $expected = rtrim(rtrim($expected, '0'), '.');
        $actual = rtrim(rtrim($actual, '0'), '.');
        return $expected === $actual;
    }

    /**
     * Compare JSON documents by decoded structure.
     */
    public function compareJson(string $expected, string $actual): bool
    {
        $expectedDecoded = json_decode($expected, true);
        $actualDecoded = json_decode($actual, true);
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
