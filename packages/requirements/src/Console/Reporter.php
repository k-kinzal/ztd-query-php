<?php

declare(strict_types=1);

namespace Requirements\Console;

use Requirements\Config\Fields;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;

final class Reporter
{
    /** @param array<string, mixed> $report */
    public function render(array $report, Options $options, InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Requirements · ' . $options->command);
        if ($options->command === 'coverage') {
            $rows = [];
            $scopes = ['Overall' => $report['overall'], ...Fields::mapping($report['sources'], 'sources')];
            if ($report['diff'] !== null) {
                $scopes['Changed units'] = $report['diff'];
            }
            foreach ($scopes as $name => $value) {
                $scope = Fields::mapping($value, 'coverage');
                $percentage = $scope['percentage'];
                $rows[] = [self::text($name), self::text($scope['total']), self::text($scope['accounted']), self::text($scope['supported']), self::text($scope['unsupported']), self::text($scope['uncovered']), is_int($percentage) || is_float($percentage) ? number_format($percentage, 2) . '%' : 'n/a'];
            }
            $io->table(['Scope', 'Units', 'Accounted', 'Supported', 'Unsupported', 'Uncovered', 'Coverage'], $rows);
            $io->note('Coverage applies only to the declared source scopes. Unsupported units are accounted for; tests are verified separately.');
        } elseif ($options->command === 'list') {
            $rows = [];
            foreach (Fields::sequence($report['items'], 'items') as $value) {
                $item = Fields::mapping($value, 'item');
                $statement = self::text($item['statement']);
                $labels = Fields::strings($item['labels'], 'labels');
                if ($labels !== []) {
                    $statement .= "\nLabels: " . self::text(implode(', ', $labels));
                }
                if ($item['reason'] !== '') {
                    $statement .= "\nReason: " . self::text($item['reason']);
                }
                $rows[] = [self::text($item['id']), $statement, self::text($item['status']), self::text($item['source'] ?? $item['origin'])];
            }
            $this->table($output, ['ID', 'Statement', 'Status', 'Source / origin'], $rows);
            $io->text(count($rows) . ' item(s). Use --json for the complete traceability records.');
        } elseif ($options->command === 'spec') {
            $rows = [];
            foreach (Fields::mapping($report['specifications'], 'specifications') as $id => $value) {
                $spec = Fields::mapping($value, 'specification');
                $rows[] = [self::text($id), self::text($spec['statement']), self::text($spec['status']), self::text($spec['tests'])];
            }
            $this->table($output, ['ID', 'Specification', 'Result', 'Tests'], $rows);
            foreach (Fields::mapping($report['specifications'], 'specifications') as $id => $value) {
                $spec = Fields::mapping($value, 'specification');
                if ($spec['message'] !== '') {
                    $io->text(self::text($id) . ': ' . self::text($spec['message']));
                }
            }
        } elseif ($options->command === 'format') {
            $changed = Fields::strings($report['changed'], 'changed');
            if ($changed !== []) {
                $io->listing(array_map(self::text(...), $changed));
            }
        }
        $errors = Fields::strings($report['errors'] ?? [], 'errors', false);
        if ($errors !== []) {
            $io->error(array_map(self::text(...), $errors));
        }
        if (($report['passed'] ?? true) === true) {
            $message = match ($options->command) {
                'lint' => self::text($report['message']),
                'check' => 'Source evidence is valid.',
                'coverage' => 'Coverage gates passed.',
                'spec' => 'All selected supported specifications passed.',
                'format' => $report['changed'] === [] ? 'No formatting changes.' : 'Documents formatted.',
                default => null,
            };
            if ($message !== null) {
                $io->success($message);
            }
        } elseif ($errors === []) {
            $io->error($options->command === 'format' ? 'Documents need formatting. Run requirements format.' : 'Specification verification failed.');
        }
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    private function table(OutputInterface $output, array $headers, array $rows): void
    {
        (new Table($output))->setHeaders($headers)->setRows($rows)
            ->setColumnMaxWidth(0, 24)->setColumnMaxWidth(1, max(24, min(72, (new Terminal())->getWidth() - 62)))
            ->setColumnMaxWidth(2, 13)->setColumnMaxWidth(3, 24)->render();
    }

    private static function text(mixed $value): string
    {
        $text = is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
        $text = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $text) ?? '';
        return OutputFormatter::escape($text);
    }
}
