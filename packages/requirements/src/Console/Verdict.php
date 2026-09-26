<?php

declare(strict_types=1);

namespace Requirements\Console;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Renders the errors of a report and its closing success or failure message.
 */
final class Verdict
{
    /**
     * Renders the errors and the closing message.
     *
     * @param SymfonyStyle $io The console style
     * @param array<string, mixed> $report The report of the command
     * @param Options $options The command and its options
     *
     * @throws InvalidInputException When the errors are not a list of strings
     */
    public function render(SymfonyStyle $io, array $report, Options $options): void
    {
        $errors = Fields::strings($report['errors'] ?? [], 'errors', false);
        if ($errors !== []) {
            $io->error(array_map(Text::plain(...), $errors));
        }
        if (($report['passed'] ?? true) === true) {
            $message = match ($options->command) {
                'lint' => Text::plain($report['message']),
                'check' => 'Source evidence is valid.',
                'coverage' => 'Coverage gates passed.',
                'spec' => $options->flag('no-test') ? 'Records displayed; tests were not run.' : 'Specification verification completed.',
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
}
