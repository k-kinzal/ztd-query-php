<?php

declare(strict_types=1);

namespace Requirements\Report;

use InvalidArgumentException;
use Requirements\Config\Fields;
use Requirements\Model\Project;

/**
 * A coverage snapshot lists every source unit in scope with the semantic fingerprint of the
 * records claiming it, and nothing else: no source text. Comparing the current analysis with
 * the snapshot of a trusted base revision yields the new or changed units, which form the
 * differential denominator, and the units the snapshot lists but the scope no longer contains.
 */
final class Snapshot
{
    public const TYPE = 'requirements-snapshot';

    /** @return array{version: int, type: string, units: array<string, array{fingerprint: string}>} */
    public function create(Analysis $analysis, Project $project): array
    {
        $units = [];
        foreach ($analysis->units as $key => $unit) {
            $units[$key] = ['fingerprint' => $unit->fingerprint($project->items)];
        }
        return ['version' => 1, 'type' => self::TYPE, 'units' => $units];
    }

    /** @return array<string, string> */
    public function read(string $file): array
    {
        $contents = is_file($file) ? file_get_contents($file) : false;
        if ($contents === false) {
            throw new InvalidArgumentException("Cannot read coverage snapshot: $file");
        }
        $data = Fields::mapping(json_decode($contents, true, 512, JSON_THROW_ON_ERROR), 'snapshot');
        if (($data['version'] ?? null) !== 1 || ($data['type'] ?? null) !== self::TYPE) {
            throw new InvalidArgumentException('Unsupported coverage snapshot. Write one with coverage --write-snapshot.');
        }
        $result = [];
        foreach (Fields::mapping($data['units'] ?? null, 'snapshot.units') as $key => $entry) {
            $unit = Fields::mapping($entry, 'snapshot.unit');
            $fingerprint = Fields::text($unit, 'fingerprint');
            if (preg_match('/^[a-f0-9]{64}$/D', $key) !== 1 || preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1) {
                throw new InvalidArgumentException('Invalid coverage snapshot unit.');
            }
            $result[$key] = $fingerprint;
        }
        return $result;
    }

    /**
     * @param array<string, string> $snapshot
     * @return array{changed: list<string>, removed: list<string>}
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
