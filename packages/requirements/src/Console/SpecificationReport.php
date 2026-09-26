<?php

declare(strict_types=1);

namespace Requirements\Console;

use Requirements\Input\InvalidInputException;
use Requirements\Model\Project;
use Requirements\Verification\Verifier;

/**
 * Reports selected specifications and applies optional test linkage gates.
 */
final class SpecificationReport
{
    /**
     * Runs selected tests and applies optional linkage gates.
     *
     * @param Project $project The loaded project
     * @param Options $options The filters and execution options
     *
     * @return array<string, mixed> The specification report
     *
     * @throws InvalidInputException When a runner uses an unknown extension
     */
    public function generate(Project $project, Options $options): array
    {
        $items = array_filter($project->items, $options->matches(...));
        $results = (new Verifier())->verify($project, $items, $options->flag('no-test'), $options->flag('all'));
        $errors = [];
        if ($options->flag('strict')) {
            if ($items === []) {
                $errors[] = 'No specifications or requirements selected.';
            }
            foreach ($items as $item) {
                if ($item->kind === 'specification' && $item->status === 'supported' && $item->tests === []) {
                    $errors[] = "$item->id: No tests linked.";
                }
            }
        }
        $passed = $errors === [];
        $rows = [];
        foreach ($results as $id => $result) {
            $passed = $passed && $result->status !== 'failed';
            $rows[$id] = [...ItemRecord::describe($items[$id]), ...$result->toArray()];
        }
        return ['passed' => $passed, 'no_test' => $options->flag('no-test'), 'strict' => $options->flag('strict'), 'all' => $options->flag('all'), 'specifications' => $rows, 'errors' => $errors];
    }
}
