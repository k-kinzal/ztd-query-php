<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Seed;

/**
 * Seeds measured against every production a root can reach.
 */
final class SeedCorpus
{
    /**
     * @param string $root Rule the seeds are generated from
     * @param list<CoverageSeed> $seeds Inputs in corpus order
     * @param array<string, string> $targets Production ID => "rule#ordinal" for every production reachable from the root
     * @param array<string, string> $failures "rule#ordinal" => why no seed could be synthesized for it
     */
    public function __construct(
        public readonly string $root,
        public readonly array $seeds,
        public readonly array $targets,
        public readonly array $failures = [],
    ) {
    }

    /**
     * Labels the targets some seed selected during derivation.
     *
     * @return list<string>
     */
    public function reached(): array
    {
        return $this->covered(array_merge([], ...array_map(static fn (CoverageSeed $seed): array => $seed->reached, $this->seeds)));
    }

    /**
     * Labels the targets some seed preserved in its output.
     *
     * @return list<string>
     */
    public function emitted(): array
    {
        return $this->covered(array_merge([], ...array_map(static fn (CoverageSeed $seed): array => $seed->emitted, $this->seeds)));
    }

    /**
     * Labels the targets no seed selected.
     *
     * @return list<string>
     */
    public function unreached(): array
    {
        return array_values(array_diff($this->targets, $this->reached()));
    }

    /**
     * Answers the largest budget any seed spends, which bounds the header a consumer must decode.
     */
    public function maximumBudget(): int
    {
        return max([0, ...array_map(static fn (CoverageSeed $seed): int => $seed->budget, $this->seeds)]);
    }

    /**
     * Keeps target labels in grammar order for the IDs given.
     *
     * @param list<string> $ids
     * @return list<string>
     */
    public function covered(array $ids): array
    {
        return array_values(array_intersect_key($this->targets, array_fill_keys($ids, true)));
    }
}
