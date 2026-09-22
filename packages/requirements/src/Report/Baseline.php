<?php

declare(strict_types=1);

namespace Requirements\Report;

use InvalidArgumentException;
use Requirements\Config\Fields;
use Requirements\Model\Project;

final class Baseline
{
    /** @return array{version: int, type: string, units: array<string, array{fingerprint: string}>} */
    public function create(Analysis $analysis, Project $project): array
    {
        $units = [];
        foreach ($analysis->units as $key => $unit) {
            $units[$key] = ['fingerprint' => $unit->fingerprint($project->items)];
        }
        return ['version' => 1, 'type' => 'requirements-coverage', 'units' => $units];
    }

    /** @return array<string, string> */
    public function read(string $file): array
    {
        $contents = is_file($file) ? file_get_contents($file) : false;
        if ($contents === false) {
            throw new InvalidArgumentException("Cannot read coverage baseline: $file");
        }
        $data = Fields::mapping(json_decode($contents, true, 512, JSON_THROW_ON_ERROR), 'baseline');
        if (($data['version'] ?? null) !== 1 || ($data['type'] ?? null) !== 'requirements-coverage') {
            throw new InvalidArgumentException('Unsupported coverage baseline.');
        }
        $result = [];
        foreach (Fields::mapping($data['units'] ?? null, 'baseline.units') as $key => $entry) {
            $unit = Fields::mapping($entry, 'baseline.unit');
            $fingerprint = Fields::text($unit, 'fingerprint');
            if (preg_match('/^[a-f0-9]{64}$/D', $key) !== 1 || preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1) {
                throw new InvalidArgumentException('Invalid coverage baseline unit.');
            }
            $result[$key] = $fingerprint;
        }
        return $result;
    }

    /**
     * @param array<string, string> $baseline
     * @return array{changed: list<string>, removed: list<string>}
     */
    public function compare(Analysis $analysis, Project $project, array $baseline): array
    {
        $changed = [];
        foreach ($analysis->units as $key => $unit) {
            if (($baseline[$key] ?? null) !== $unit->fingerprint($project->items)) {
                $changed[] = $key;
            }
        }
        return ['changed' => $changed, 'removed' => array_values(array_diff(array_keys($baseline), array_keys($analysis->units)))];
    }
}
