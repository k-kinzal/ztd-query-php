<?php

declare(strict_types=1);

namespace Deriver\Project;

use Deriver\Result\Frontier;

/**
 * Versioned immutable manifest of source, models, environment, and target assumptions.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Project\ProjectSnapshot('s', [], [], new \Deriver\Project\TargetProfile(), false, 'none'))->id // => 's'
 */
final class ProjectSnapshot
{
    /**
     * @param string $id id
     * @param array<string, string> $sources sources
     * @param array<string, string> $models models
     * @param TargetProfile $target target
     * @param bool $closedWorld closedWorld
     * @param string $environmentVersion environmentVersion
     * @param list<Frontier> $diagnostics diagnostics
     * @param array<string, string> $dependencyVersions Captured dependency package versions
     * @param array<string, string> $sourceModes Files supplying declarations without executable bodies
     */
    public function __construct(
        public readonly string $id,
        public readonly array $sources,
        public readonly array $models,
        public readonly TargetProfile $target,
        public readonly bool $closedWorld,
        public readonly string $environmentVersion,
        public readonly array $diagnostics = [],
        public readonly array $dependencyVersions = [],
        public readonly array $sourceModes = [],
    ) {
    }
}
