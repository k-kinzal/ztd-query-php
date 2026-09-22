<?php

declare(strict_types=1);

namespace Requirements\Console;

use InvalidArgumentException;
use Requirements\Model\Item;
use Requirements\Model\Project;
use Requirements\Report\Analyzer;
use Requirements\Report\Coverage;
use Requirements\Test\Verifier;

final class Executor
{
    /** @return array<string, mixed> */
    public function execute(Project $project, Options $options): array
    {
        if ($options->command === 'lint') {
            return ['passed' => true, 'message' => count($project->items) . ' items validated.'];
        }
        if ($options->command === 'format') {
            $changed = (new Formatter())->format($project->files, $options->flag('check'), $project->markdown);
            return ['passed' => !$options->flag('check') || $changed === [], 'changed' => $changed];
        }
        if ($options->command === 'spec') {
            $items = array_filter($project->items, $options->matches(...));
            $results = (new Verifier())->verify($project, $items, $options->flag('no-test'));
            $passed = $results !== [];
            $rows = [];
            foreach ($results as $id => $result) {
                $passed = $passed && in_array($result->status, ['passed', 'unsupported', 'not-applicable', 'not-run'], true);
                $rows[$id] = [...$this->describe($items[$id]), ...$result->toArray()];
            }
            return ['passed' => $passed, 'no_test' => $options->flag('no-test'), 'specifications' => $rows, 'errors' => $results === [] ? ['No specifications or requirements selected.'] : []];
        }
        if (!in_array($options->command, ['check', 'coverage'], true)) {
            throw new InvalidArgumentException('Unknown command: ' . $options->command);
        }
        $analysis = (new Analyzer())->analyze($project, $options->flag('live'));
        if ($options->command === 'check') {
            return ['passed' => $analysis->errors === [], 'mode' => $options->flag('live') ? 'live' : 'configured', 'errors' => $analysis->errors, 'evidence' => $analysis->evidence];
        }
        $report = (new Coverage())->report($project, $analysis, $options->text('baseline'), $options->flag('allow-removed'), $options->percentage('min-coverage'), $options->percentage('min-diff-coverage'));
        $report['mode'] = $options->flag('live') ? 'live' : 'configured';
        $baseline = $options->text('write-baseline');
        if ($baseline !== null) {
            if ($analysis->errors !== []) {
                throw new InvalidArgumentException('Cannot write a baseline with invalid source evidence.');
            }
            if (file_put_contents($baseline, json_encode((new \Requirements\Report\Baseline())->create($analysis, $project), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n") === false) {
                throw new InvalidArgumentException("Cannot write baseline: $baseline");
            }
        }
        return $report;
    }

    /** @return array<string, mixed> */
    private function describe(Item $item): array
    {
        return ['id' => $item->id, 'kind' => $item->kind, 'statement' => $item->statement, 'support' => $item->status, 'source' => $item->source?->id, 'origin' => $item->origin, 'reason' => $item->reason, 'labels' => $item->labels, 'category' => $item->category, 'requirements' => $item->requirements, 'related' => $item->related, 'design' => $item->data['design'] ?? [], 'metadata' => $item->data['metadata'] ?? [], 'test_references' => $item->data['tests'] ?? []];
    }

}
