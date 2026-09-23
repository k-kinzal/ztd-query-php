<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Seed;

/**
 * One replayable byte input together with what it generated and which productions it exercised.
 */
final class CoverageSeed
{
    /**
     * @param string $input Bytes BytePlanCompiler decodes under the corpus contract
     * @param string|null $target "rule#ordinal" of the production the seed was synthesized to reach, or null for a replayed input
     * @param int $budget Expansions the decoded plan spends
     * @param string $sql Statement the seed generates
     * @param list<string> $reached Production IDs the derivation selected
     * @param list<string> $emitted Production IDs preserved in the output
     */
    public function __construct(
        public readonly string $input,
        public readonly ?string $target,
        public readonly int $budget,
        public readonly string $sql,
        public readonly array $reached,
        public readonly array $emitted,
    ) {
    }

    /**
     * Names the seed after its target so a corpus stays readable, or after its bytes when it has none.
     */
    public function name(): string
    {
        return $this->target === null ? hash('sha256', $this->input) : str_replace('#', '-', $this->target);
    }
}
