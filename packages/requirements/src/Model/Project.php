<?php

declare(strict_types=1);

namespace Requirements\Model;

use Requirements\Test\RunnerConfig;

/**
 * A loaded project: its items and sources, runners, extensions and coverage gates.
 */
final class Project
{
    /**
     * @param string $directory The directory of the configuration file
     * @param array<string, Item> $items Every item by ID
     * @param array<string, Source> $sources Every declared source by ID
     * @param array<string, RunnerConfig> $runners Configured runners by name
     * @param array<string, string> $sourceExtensions Source extension classes by format
     * @param array<string, string> $runnerExtensions Runner extension classes by name
     * @param float $minimum The overall coverage threshold in percent
     * @param float $diffMinimum The threshold for units new or changed since a snapshot
     * @param array<string, float> $sourceThresholds Coverage thresholds by source ID
     * @param list<string> $files The configuration file followed by the definition files
     * @param array<string, mixed> $markdown The markdown options of the configuration
     */
    public function __construct(
        public readonly string $directory,
        public readonly array $items,
        public readonly array $sources,
        public readonly array $runners,
        public readonly array $sourceExtensions,
        public readonly array $runnerExtensions,
        public readonly float $minimum,
        public readonly float $diffMinimum,
        public readonly array $sourceThresholds,
        public readonly array $files,
        public readonly array $markdown = [],
    ) {
    }
}
