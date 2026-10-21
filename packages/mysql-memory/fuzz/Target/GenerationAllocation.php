<?php

declare(strict_types=1);

namespace Fuzz\Target;

/**
 * Compares one observed physical-allocation diagnostic in MySQL 5.7.44.
 *
 * Two independent instances ran the same binary prepared SUM generation template
 * on four fresh connections each. Its absent/existing/removed destination history
 * always retained error 1111 after removal. The first connection additionally
 * reported error 34 with capacity 1; later connections did not. Memory has no such
 * allocator and reports its own generation error. Raw observations are retained
 * in fixtures/generation-allocation-5.7.json.
 *
 * This contract is called only by the binary generated-column lifecycle test.
 * It is not a general SQL-error normalizer. Counters, connection state and every
 * other diagnostic remain exact, including unobserved capacity values.
 */
final class GenerationAllocation
{
    /**
     * The bounded physical-allocation comparison contract.
     */
    public const CONTRACT = 'legacy-invalidated-generation-allocation';

    /**
     * @param list<array{array<string, mixed>, array<string, mixed>, array<string, mixed>}> $history Absent, existing and removed binary-template observations
     * @return list<array{array<string, mixed>, array<string, mixed>, array<string, mixed>}> A comparison copy retaining the generation condition
     */
    public function comparable(array $history, string $release): array
    {
        $generation = [1111, 'HY000', 'Invalid use of group function'];
        $capacity = [34, 'HY000', 'Memory capacity exceeded (capacity 1 bytes)'];
        if ($release !== '5.7.44' || count($history) !== 3 || $history[0][0] !== ['error' => $generation] || $history[1][0] !== ['affected' => 0]) {
            return $history;
        }
        if ($history[2][0] !== ['error' => $capacity] || $history[2][1] !== ['warnings' => [['Error', '34', $capacity[2]], ['Error', '1111', $generation[2]]], 'disconnected' => false]) {
            return $history;
        }
        $history[2][0] = ['error' => $generation];
        $history[2][1]['warnings'] = [['Error', '1111', $generation[2]]];
        return $history;
    }
}
