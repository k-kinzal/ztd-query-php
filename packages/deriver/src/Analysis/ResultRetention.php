<?php

declare(strict_types=1);

namespace Deriver\Analysis;

use Deriver\Result\DerivationResult;
use WeakMap;

/**
 * Keeps a bounded working set; callers retain ownership of larger or older result graphs.
 * @visibility root
 */
final class ResultRetention
{
    /**
     * @var array<string, DerivationResult|\Deriver\Result\Candidates\CandidateCollection> Recent small results, including interrupted observations
     */
    private array $recent = [];

    /**
     * Sets the maximum number of recent small results retained by the session.
     */
    public function __construct(public readonly int $capacity = 32)
    {
    }

    /**
     * Releases all strongly owned result graphs.
     */
    public function clear(): void
    {
        $this->recent = [];
    }

    /**
     * Caps both the number and structural size of retained graphs without serializing them.
     * @param DerivationResult|\Deriver\Result\Candidates\CandidateCollection $result New observation
     */
    public function remember(DerivationResult|\Deriver\Result\Candidates\CandidateCollection $result): void
    {
        if ($this->capacity === 0 || !$this->small($result)) {
            return;
        }
        unset($this->recent[$result->reference->id]);
        $this->recent[$result->reference->id] = $result;
        if (count($this->recent) > $this->capacity) {
            array_shift($this->recent);
        }
    }

    /**
     * Bounds the complete retained object graph, arrays, and literal payloads, including query inputs.
     * @param DerivationResult|\Deriver\Result\Candidates\CandidateCollection $result Candidate result
     * @return bool Whether it fits 4,096 visited entries and one MiB of string payloads
     */
    public function small(DerivationResult|\Deriver\Result\Candidates\CandidateCollection $result): bool
    {
        $seen = new WeakMap();
        $pending = [$result];
        $nodes = 0;
        $bytes = 0;
        while ($pending !== []) {
            $value = array_pop($pending);
            if (++$nodes > 4096) {
                return false;
            }
            if (is_object($value)) {
                if (isset($seen[$value])) {
                    continue;
                }
                $seen[$value] = true;
                $value = get_object_vars($value);
            }
            if (is_array($value)) {
                if (count($value) + $nodes + count($pending) > 4096) {
                    return false;
                }
                foreach ($value as $key => $entry) {
                    $bytes += is_string($key) ? strlen($key) : 0;
                    $pending[] = $entry;
                }
            } elseif (is_string($value)) {
                $bytes += strlen($value);
            }
            if ($bytes > 1048576) {
                return false;
            }
        }
        return true;
    }
}
