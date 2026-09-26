<?php

declare(strict_types=1);

namespace Requirements\Report;

use JsonException;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Project;

/**
 * Builds the coverage report and applies the overall, per-source and differential gates.
 */
final class Coverage
{
    /**
     * Builds the coverage report of an analysis.
     *
     * @param Project $project The loaded project
     * @param Analysis $analysis The analysis of its sources
     * @param string|null $snapshotFile The coverage snapshot of a trusted base revision to compare with
     * @param bool $allowRemoved Whether units the snapshot lists may be missing from the scope
     * @param float|null $minimum The overall threshold, overriding the configured one
     * @param float|null $diffMinimum The differential threshold, overriding the configured one
     *
     * @return array<string, mixed> The report; "passed" is false when a gate fails
     *
     * @throws InvalidInputException When the snapshot cannot be read or is not a coverage snapshot
     * @throws JsonException When the snapshot is not JSON or a record cannot be encoded
     */
    public function report(Project $project, Analysis $analysis, ?string $snapshotFile = null, bool $allowRemoved = false, ?float $minimum = null, ?float $diffMinimum = null): array
    {
        $errors = $analysis->errors;
        $overall = $analysis->summary();
        $minimum ??= $project->minimum;
        $diffMinimum ??= $project->diffMinimum;
        if (($overall['percentage'] ?? 0.0) < $minimum) {
            $errors[] = "Overall source coverage is below $minimum%.";
        }
        $sources = [];
        foreach ($analysis->scopes as $id => $keys) {
            $sources[$id] = $analysis->summary($keys);
            $threshold = $project->sourceThresholds[$id] ?? 0.0;
            if (($sources[$id]['percentage'] ?? 0.0) < $threshold) {
                $errors[] = "$id: source coverage is below $threshold%.";
            }
        }
        $diff = null;
        $removed = [];
        if ($snapshotFile !== null) {
            $snapshot = new Snapshot();
            $comparison = $snapshot->compare($analysis, $project, $snapshot->read($snapshotFile));
            $diff = $analysis->summary($comparison['changed']);
            $removed = $comparison['removed'];
            if ($diff['percentage'] !== null && $diff['percentage'] < $diffMinimum) {
                $errors[] = "Differential source coverage is below $diffMinimum%.";
            }
            if ($removed !== [] && !$allowRemoved) {
                $errors[] = 'Source units listed in the snapshot were removed. Review scope changes before using --allow-removed.';
            }
        } elseif ($diffMinimum > 0) {
            $errors[] = 'A positive differential threshold requires --snapshot.';
        }
        $units = [];
        foreach ($analysis->units as $key => $unit) {
            $units[$key] = $unit->toArray($project->items);
        }
        return ['version' => 1, 'type' => 'requirements-coverage', 'overall' => $overall, 'sources' => $sources, 'diff' => $diff, 'removed' => $removed, 'units' => $units, 'errors' => $errors, 'passed' => $errors === []];
    }
}
