<?php

declare(strict_types=1);

namespace Requirements\Config;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Source;

/**
 * Reads the per-source coverage thresholds of the configuration.
 */
final class CoverageThresholds
{
    /**
     * Reads coverage.sources.
     *
     * @param mixed $value The decoded coverage.sources mapping
     * @param array<string, Source> $sources The declared sources by ID
     *
     * @return array<string, float> The thresholds by source ID
     *
     * @throws InvalidInputException When a threshold names an unknown source or is not a percentage
     */
    public function read(mixed $value, array $sources): array
    {
        $thresholds = [];
        foreach (Fields::mapping($value, 'coverage.sources') as $id => $threshold) {
            if (!isset($sources[$id])) {
                throw new InvalidInputException("Unknown source threshold: $id");
            }
            $thresholds[$id] = Fields::percentage($threshold, "coverage.sources.$id");
        }
        return $thresholds;
    }
}
