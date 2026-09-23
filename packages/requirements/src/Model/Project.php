<?php

declare(strict_types=1);

namespace Requirements\Model;

use Requirements\Test\RunnerConfig;

final class Project
{
    /**
     * @param array<string, Item> $items
     * @param array<string, Source> $sources
     * @param array<string, RunnerConfig> $runners
     * @param array<string, string> $sourceExtensions
     * @param array<string, string> $runnerExtensions
     * @param array<string, float> $sourceThresholds
     * @param list<string> $files
     * @param array<string, mixed> $markdown
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
