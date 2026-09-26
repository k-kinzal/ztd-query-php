<?php

declare(strict_types=1);

namespace Requirements\Console;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;

/**
 * Renders the spec report: one row per item with its statement, result and passing / linked
 * targets, followed by the failure messages.
 */
final class SpecificationTable
{
    /**
     * Renders the specification table and the messages of failed items.
     *
     * @param SymfonyStyle $io The console style
     * @param OutputInterface $output Receives the table
     * @param array<string, mixed> $report The spec report
     *
     * @throws InvalidInputException When a row does not have the shape of a specification record
     */
    public function render(SymfonyStyle $io, OutputInterface $output, array $report): void
    {
        $rows = [];
        foreach (Fields::mapping($report['specifications'], 'specifications') as $id => $value) {
            $spec = Fields::mapping($value, 'specification');
            $statement = Text::plain($spec['statement']);
            $statement .= "\n" . Text::plain($spec['kind']) . ' · ' . Text::plain($spec['source'] ?? $spec['origin']);
            if ($spec['category'] !== '') {
                $statement .= ' · ' . Text::plain($spec['category']);
            }
            $labels = Fields::strings($spec['labels'], 'labels');
            if ($labels !== []) {
                $statement .= "\nLabels: " . Text::plain(implode(', ', $labels));
            }
            if ($spec['reason'] !== '') {
                $statement .= "\nReason: " . Text::plain($spec['reason']);
            }
            $ratio = Text::plain($spec['passed_targets'] ?? '-') . '/' . Text::plain($spec['total_targets']);
            $rows[] = [Text::plain($id), $statement, Text::plain($spec['status']), $ratio];
        }
        $this->table($output, ['ID', 'Statement', 'Result', 'Tests'], $rows);
        $io->text(count($rows) . ' item(s). Tests: passed / linked targets. Use --json for execution counts and complete traceability records.');
        foreach (Fields::mapping($report['specifications'], 'specifications') as $id => $value) {
            $spec = Fields::mapping($value, 'specification');
            if ($spec['message'] !== '') {
                $io->text(Text::plain($id) . ': ' . Text::plain($spec['message']));
            }
        }
    }

    /**
     * Renders rows with the statement column wrapped to the terminal width.
     *
     * @param OutputInterface $output Receives the table
     * @param list<string> $headers The ID, statement, result and tests headers
     * @param list<list<string>> $rows The rows
     */
    public function table(OutputInterface $output, array $headers, array $rows): void
    {
        $widths = [];
        foreach ([0 => 24, 2 => 14, 3 => 12] as $column => $maximum) {
            $width = mb_strwidth($headers[$column]);
            foreach ($rows as $row) {
                $width = max($width, mb_strwidth($row[$column]));
            }
            $widths[$column] = min($maximum, $width);
        }
        (new Table($output))->setHeaders($headers)->setRows($rows)
            ->setColumnMaxWidth(0, $widths[0])->setColumnMaxWidth(1, max(24, min(72, (new Terminal())->getWidth() - array_sum($widths) - 13)))
            ->setColumnMaxWidth(2, $widths[2])->setColumnMaxWidth(3, $widths[3])->render();
    }
}
