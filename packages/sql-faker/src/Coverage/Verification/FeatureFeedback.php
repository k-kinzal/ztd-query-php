<?php

declare(strict_types=1);

namespace SqlFaker\Coverage\Verification;

/**
 * Maps semantic features to a separate negative edge range for native corpus guidance.
 * Persistence retains exact IDs; only this lossy fuzz-feedback map uses bounded hashes.
 * @phpstan-import-type Trace from \SqlFaker\Coverage\GenerationTrace
 */
final class FeatureFeedback
{
    /**
     * Stable IDs are independent of observation order, random spelling and saved corpus contents.
     * @param Trace $trace
     * @return array<int, int>
     */
    public function edges(array $trace, ?string $verdict = null): array
    {
        $edges = [];
        $groups = $trace['features'];
        if ($verdict !== null) {
            $groups['production'] = $trace['emittedIds'];
        }
        foreach ($groups as $kind => $ids) {
            foreach ($ids as $id) {
                $key = serialize([$verdict, $kind, $id]);
                $edge = -1000000 - (int) hexdec(substr(hash('sha256', $key), 0, 7));
                $edges[$edge] = 1;
            }
        }
        return $edges;
    }
}
