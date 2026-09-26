<?php

declare(strict_types=1);

namespace Requirements\Console;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Renders the coverage report as one row per scope: overall, each source and changed units.
 */
final class CoverageTable
{
    /**
     * Renders the coverage table and its note.
     *
     * @param SymfonyStyle $io The console style
     * @param array<string, mixed> $report The coverage report
     *
     * @throws InvalidInputException When a scope summary is not a mapping
     */
    public function render(SymfonyStyle $io, array $report): void
    {
        $rows = [];
        $scopes = ['Overall' => $report['overall'], ...Fields::mapping($report['sources'], 'sources')];
        if ($report['diff'] !== null) {
            $scopes['Changed units'] = $report['diff'];
        }
        foreach ($scopes as $name => $value) {
            $scope = Fields::mapping($value, 'coverage');
            $percentage = $scope['percentage'];
            $rows[] = [Text::plain($name), Text::plain($scope['total']), Text::plain($scope['accounted']), Text::plain($scope['supported']), Text::plain($scope['unsupported']), Text::plain($scope['uncovered']), is_int($percentage) || is_float($percentage) ? number_format($percentage, 2) . '%' : 'n/a'];
        }
        $io->table(['Scope', 'Units', 'Accounted', 'Supported', 'Unsupported', 'Uncovered', 'Coverage'], $rows);
        $io->note('Coverage applies only to the declared source scopes. Unsupported units are accounted for; tests are verified separately.');
    }
}
