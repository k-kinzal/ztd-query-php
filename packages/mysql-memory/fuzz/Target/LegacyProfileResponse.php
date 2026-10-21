<?php

declare(strict_types=1);

namespace Fuzz\Target;

/**
 * Compares the observed decoding variation of a 5.6 binary function response.
 * SHOW PROFILE ALL inside a function interrupts its caller's row stream. Two
 * independent 5.6.51 instances exposed either no rows or seventeen NULL rows to
 * mysqli for the same request history. Both disconnected when reading conditions.
 * The raw observations are retained in fixtures/profile-response-5.6.json.
 *
 * Only the direct profiling-function history calls this contract. It does not
 * normalize ordinary query results, other row counts or other connection states.
 * Column metadata remains exact, and the emulator returns its own decoded result.
 */
final class LegacyProfileResponse
{
    /**
     * The bounded driver-decoding comparison contract.
     */
    public const CONTRACT = 'legacy-binary-function-profile-response';

    /**
     * @param array<string, mixed> $observation The unchanged raw driver observation
     * @return array<string, mixed> A comparison copy for the observed response variants
     */
    public function comparable(array $observation, string $release, bool $prepared): array
    {
        if ($release !== '5.6.51' || !$prepared || count($observation) !== 3 || ($observation['disconnected'] ?? false) !== true || !array_key_exists('warnings', $observation) || $observation['warnings'] !== null) {
            return $observation;
        }
        $results = $observation['results'] ?? null;
        if (!is_array($results) || count($results) !== 1 || !is_array($results[0] ?? null) || count($results[0]) !== 2 || ($results[0]['rows'] ?? null) !== array_fill(0, 17, [null])) {
            return $observation;
        }
        $columns = $results[0]['columns'] ?? null;
        if (!is_array($columns) || count($columns) !== 1 || !is_array($columns[0] ?? null)) {
            return $observation;
        }
        $column = $columns[0];
        if (($column['type'] ?? null) !== 3 || ($column['charsetnr'] ?? null) !== 63 || ($column['length'] ?? null) !== 11 || ($column['flags'] ?? null) !== 32768 || ($column['decimals'] ?? null) !== 0) {
            return $observation;
        }
        $results[0]['rows'] = [];
        $observation['results'] = $results;

        return $observation;
    }
}
