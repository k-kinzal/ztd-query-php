<?php

declare(strict_types=1);

namespace Requirements\Console;

use InvalidArgumentException;
use Requirements\Config\Loader;
use Requirements\Model\Item;
use Requirements\Model\Project;
use Requirements\Report\Analyzer;
use Requirements\Report\Coverage;
use Requirements\Test\Verifier;
use Throwable;

final class Application
{
    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        try {
            $options = Options::parse($arguments);
            if (in_array($options->command, ['help', '--help', '-h'], true)) {
                echo "requirements <check|coverage|spec|list|lint|format> [--config requirements.yaml] [--json]\n";
                echo "list/spec filters: --id --label --category --source --status --kind --origin --without-source\n";
                echo "check/coverage: --live; coverage: --baseline FILE --write-baseline FILE --min-coverage N --min-diff-coverage N --allow-removed\n";
                echo "format: --check; exit codes: 0 success, 1 failed check/gate/spec/format, 2 configuration or execution error\n";
                return 0;
            }
            $project = (new Loader())->load($options->text('config', 'requirements.yaml') ?? 'requirements.yaml');
            $report = $this->execute($project, $options);
            $this->output($report, $options->flag('json'));
            return ($report['passed'] ?? true) === true ? 0 : 1;
        } catch (Throwable $error) {
            if (in_array('--json', $arguments, true)) {
                echo json_encode(['passed' => false, 'errors' => [$error->getMessage()]], JSON_THROW_ON_ERROR) . "\n";
            } else {
                fwrite(STDERR, $error->getMessage() . "\n");
            }
            return 2;
        }
    }

    /** @return array<string, mixed> */
    private function execute(Project $project, Options $options): array
    {
        if ($options->command === 'lint') {
            return ['passed' => true, 'message' => count($project->items) . ' items validated.'];
        }
        if ($options->command === 'format') {
            $changed = (new Formatter())->format($project->files, $options->flag('check'), $project->markdown);
            return ['passed' => !$options->flag('check') || $changed === [], 'changed' => $changed];
        }
        if (in_array($options->command, ['list', 'spec'], true)) {
            $items = array_filter($project->items, $options->matches(...));
            if ($options->command === 'list') {
                return ['passed' => true, 'items' => array_values(array_map($this->describe(...), $items))];
            }
            $results = (new Verifier())->verify($project, $items);
            $passed = $results !== [];
            $rows = [];
            foreach ($results as $id => $result) {
                $passed = $passed && in_array($result->status, ['passed', 'unsupported'], true);
                $rows[$id] = ['statement' => $items[$id]->statement, ...$result->toArray()];
            }
            return ['passed' => $passed, 'specifications' => $rows, 'errors' => $results === [] ? ['No specifications selected.'] : []];
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
        return ['id' => $item->id, 'kind' => $item->kind, 'statement' => $item->statement, 'status' => $item->status, 'source' => $item->source?->id, 'origin' => $item->origin, 'reason' => $item->reason, 'labels' => $item->labels, 'category' => $item->category, 'requirements' => $item->requirements, 'related' => $item->related, 'design' => $item->data['design'] ?? [], 'metadata' => $item->data['metadata'] ?? [], 'tests' => $item->data['tests'] ?? []];
    }

    /** @param array<string, mixed> $report */
    private function output(array $report, bool $json): void
    {
        if ($json) {
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            return;
        }
        unset($report['units'], $report['evidence']);
        echo \Symfony\Component\Yaml\Yaml::dump($report, 8, 2);
    }
}
