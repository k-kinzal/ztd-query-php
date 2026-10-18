<?php

declare(strict_types=1);

namespace Deriver\Deriver\Candidate;

/**
 * Versioned immutable manifest of source, evaluators, environment, and target assumptions.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Deriver\Candidate\Snapshot('s', [], [], new \Deriver\Deriver\Evaluator\TargetProfile(), hash('sha256', '[]')))->id // => 's'
 */
final class Snapshot
{
    /**
     * @param string $id id
     * @param array<string, string> $sources sources
     * @param array<string, string> $evaluators evaluators
     * @param \Deriver\Deriver\Evaluator\TargetProfile $target target
     * @param string $environmentHash Content hash of explicitly supplied inputs
     * @param list<\Deriver\Deriver\Evaluator\Evidence\Frontier> $diagnostics diagnostics
     * @param array<string, string> $sourceModes Files supplying declarations without executable bodies
     */
    public function __construct(
        public readonly string $id,
        public readonly array $sources,
        public readonly array $evaluators,
        public readonly \Deriver\Deriver\Evaluator\TargetProfile $target,
        public readonly string $environmentHash,
        public readonly array $diagnostics = [],
        public readonly array $sourceModes = [],
    ) {
    }
}
