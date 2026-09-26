<?php

declare(strict_types=1);

namespace Requirements\Console;

use JsonException;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Project;
use Requirements\Report\Analyzer;
use Requirements\Report\Coverage;
use Requirements\Report\Snapshot;
use RuntimeException;

/**
 * Executes a command against a loaded project and returns its report.
 *
 * Every report has "passed"; the other fields depend on the command.
 */
final class Executor
{
    /**
     * Executes a command.
     *
     * @param Project $project The loaded project
     * @param Options $options The command and its options
     *
     * @return array<string, mixed> The report
     *
     * @throws InvalidInputException When the command is unknown, an option is invalid or a coverage snapshot cannot be used
     * @throws JsonException When a document or the snapshot cannot be encoded
     * @throws RuntimeException When a definition cannot be formatted
     */
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
            return (new SpecificationReport())->generate($project, $options);
        }
        if (!in_array($options->command, ['check', 'coverage'], true)) {
            throw new InvalidInputException('Unknown command: ' . $options->command);
        }
        $analysis = (new Analyzer())->analyze($project, $options->flag('live'));
        if ($options->command === 'check') {
            return ['passed' => $analysis->errors === [], 'mode' => $options->flag('live') ? 'live' : 'configured', 'errors' => $analysis->errors, 'evidence' => $analysis->evidence];
        }
        $report = (new Coverage())->report($project, $analysis, $options->text('snapshot'), $options->flag('allow-removed'), $options->percentage('min-coverage'), $options->percentage('min-diff-coverage'));
        $report['mode'] = $options->flag('live') ? 'live' : 'configured';
        $snapshot = $options->text('write-snapshot');
        if ($snapshot !== null) {
            if ($analysis->errors !== []) {
                throw new InvalidInputException('Cannot write a coverage snapshot with invalid source evidence.');
            }
            if (file_put_contents($snapshot, json_encode((new Snapshot())->create($analysis, $project), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n") === false) {
                throw new InvalidInputException("Cannot write coverage snapshot: $snapshot");
            }
        }
        return $report;
    }
}
