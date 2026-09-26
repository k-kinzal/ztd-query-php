<?php

declare(strict_types=1);

namespace Requirements\Console;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Renders a command report as terminal tables and messages.
 *
 * Every value written comes from definitions or sources, so control characters are removed
 * and console markup is escaped.
 */
final class Reporter
{
    /**
     * Renders a report.
     *
     * @param array<string, mixed> $report The report of the command
     * @param Options $options The command and its options
     * @param InputInterface $input The parsed command line
     * @param OutputInterface $output Receives the tables and messages
     *
     * @throws InvalidInputException When the report does not have the shape of its command
     */
    public function render(array $report, Options $options, InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Requirements · ' . $options->command);
        if ($options->command === 'coverage') {
            (new CoverageTable())->render($io, $report);
        } elseif ($options->command === 'spec') {
            (new SpecificationTable())->render($io, $output, $report);
        } elseif ($options->command === 'format') {
            $changed = Fields::strings($report['changed'], 'changed');
            if ($changed !== []) {
                $io->listing(array_map(Text::plain(...), $changed));
            }
        }
        (new Verdict())->render($io, $report, $options);
    }
}
