<?php

declare(strict_types=1);

namespace Requirements\Report;

use JsonException;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Project;

/**
 * A coverage snapshot lists every source unit in scope with the semantic fingerprint of the
 * records claiming it, and nothing else: no source text. Comparing the current analysis with
 * the snapshot of a trusted base revision yields the new or changed units, which form the
 * differential denominator, and the units the snapshot lists but the scope no longer contains.
 */
final class Snapshot
{
    /**
     * The type written in every coverage snapshot.
     */
    public const TYPE = 'requirements-snapshot';

    /**
     * Creates the snapshot of an analysis.
     *
     * @param Analysis $analysis The analysis
     * @param Project $project The analyzed project
     *
     * @return array{version: int, type: string, units: array<string, array{fingerprint: string}>} The snapshot document
     *
     * @throws JsonException When a record cannot be encoded
     */
    public function create(Analysis $analysis, Project $project): array
    {
        $units = [];
        foreach ($analysis->units as $key => $unit) {
            $units[$key] = ['fingerprint' => $unit->fingerprint($project->items)];
        }
        return ['version' => 1, 'type' => self::TYPE, 'units' => $units];
    }

    /**
     * Reads a snapshot file.
     *
     * @param string $file The snapshot file
     *
     * @return array<string, string> The fingerprints by unit key
     *
     * @throws InvalidInputException When the file cannot be read, is not a coverage snapshot or lists an invalid unit
     * @throws JsonException When the file is not JSON
     */
    public function read(string $file): array
    {
        $contents = is_file($file) ? file_get_contents($file) : false;
        if ($contents === false) {
            throw new InvalidInputException("Cannot read coverage snapshot: $file");
        }
        $data = Fields::mapping(json_decode($contents, true, 512, JSON_THROW_ON_ERROR), 'snapshot');
        if (($data['version'] ?? null) !== 1 || ($data['type'] ?? null) !== self::TYPE) {
            throw new InvalidInputException('Unsupported coverage snapshot. Write one with coverage --write-snapshot.');
        }
        $result = [];
        foreach (Fields::mapping($data['units'] ?? null, 'snapshot.units') as $key => $entry) {
            $unit = Fields::mapping($entry, 'snapshot.unit');
            $fingerprint = Fields::text($unit, 'fingerprint');
            if (preg_match('/^[a-f0-9]{64}$/D', $key) !== 1 || preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1) {
                throw new InvalidInputException('Invalid coverage snapshot unit.');
            }
            $result[$key] = $fingerprint;
        }
        return $result;
    }

    /**
     * Compares an analysis with a snapshot.
     *
     * @param Analysis $analysis The current analysis
     * @param Project $project The analyzed project
     * @param array<string, string> $snapshot The fingerprints by unit key
     *
     * @return array{changed: list<string>, removed: list<string>} The new or changed unit keys and the unit keys no longer in scope
     *
     * @throws JsonException When a record cannot be encoded
     */
    public function compare(Analysis $analysis, Project $project, array $snapshot): array
    {
        $changed = [];
        foreach ($analysis->units as $key => $unit) {
            if (($snapshot[$key] ?? null) !== $unit->fingerprint($project->items)) {
                $changed[] = $key;
            }
        }
        return ['changed' => $changed, 'removed' => array_values(array_diff(array_keys($snapshot), array_keys($analysis->units)))];
    }
}
