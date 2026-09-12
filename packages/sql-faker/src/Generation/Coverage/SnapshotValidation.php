<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Coverage;

use JsonException;
use stdClass;

/**
 * Validates persistence data before it can affect cumulative measurements.
 *
 * @phpstan-import-type Snapshot from GrammarCoverage
 * @phpstan-import-type History from GrammarCoverage
 * @visibility root
 */
final class SnapshotValidation
{
    /**
     * Reads saved identity and production sets while rejecting corrupt JSON.
     *
     * @return History
     * @throws CoverageException When JSON or required fields are invalid
     */
    public function decode(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CoverageException('Invalid coverage snapshot JSON.', 0, $exception);
        }
        if (!is_array($data) || ($data['formatVersion'] ?? null) !== 1) {
            throw new CoverageException('Unsupported coverage snapshot format.');
        }
        foreach (['grammarFingerprint', 'generatorRevision', 'root', 'inventoryDigest'] as $field) {
            if (!is_string($data[$field] ?? null)) {
                throw new CoverageException('Invalid coverage identity field: ' . $field);
            }
        }
        if (!is_array($data['cumulative'] ?? null) || !is_array($data['checkpoint'] ?? null)) {
            throw new CoverageException('Missing coverage sets or checkpoint.');
        }
        foreach (['reachedIds', 'emittedIds'] as $field) {
            if (!is_array($data['cumulative'][$field] ?? null)
                || !array_is_list($data['cumulative'][$field])
                || count(array_filter($data['cumulative'][$field], is_string(...))) !== count($data['cumulative'][$field])) {
                throw new CoverageException('Invalid coverage ID collection: ' . $field);
            }
        }
        if (!$this->checkpoint((object) $data['checkpoint'])) {
            throw new CoverageException('Invalid coverage checkpoint diagnostics.');
        }
        /**
         * @var History $data
         */
        return $data;
    }

    /**
     * Validates diagnostic fields without treating past counters as current observations.
     */
    public function checkpoint(stdClass $checkpoint): bool
    {
        return is_string($checkpoint->savedAt ?? null) && is_string($checkpoint->runId ?? null)
            && is_int($checkpoint->generationsObservedInRun ?? null)
            && is_bool($checkpoint->generationInProgress ?? null);
    }

    /**
     * Rejects mismatched histories and IDs outside the reconstructed inventory.
     *
     * @param History $incoming
     * @param Snapshot $expected
     * @throws CoverageException When history cannot be merged safely
     */
    public function compatible(array $incoming, array $expected, GrammarCoverageInventory $inventory): void
    {
        foreach (['formatVersion', 'grammarFingerprint', 'generatorRevision', 'root', 'inventoryDigest'] as $field) {
            if ($incoming[$field] !== $expected[$field]) {
                throw new CoverageException('Incompatible coverage snapshot: ' . $field);
            }
        }
        foreach (['reachedIds', 'emittedIds'] as $field) {
            if (array_diff($incoming['cumulative'][$field], array_keys($inventory->entries)) !== []) {
                throw new CoverageException('Coverage snapshot contains unknown production IDs.');
            }
        }
        if (array_diff($incoming['cumulative']['emittedIds'], $incoming['cumulative']['reachedIds']) !== []) {
            throw new CoverageException('Emitted coverage must be a subset of reached coverage.');
        }
    }
}
