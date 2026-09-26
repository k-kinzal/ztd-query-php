<?php

declare(strict_types=1);

namespace Requirements\Verification;

use Requirements\Input\InvalidInputException;
use Requirements\Model\Item;
use Requirements\Model\Project;
use Requirements\Test\Registry;
use Requirements\Test\TestResult;

/**
 * Executes distinct targets selected by supported specifications and execution policies.
 */
final class TestExecution
{
    /**
     * Runs automatic targets, or includes manual targets when requested.
     *
     * @param Project $project The runner configuration
     * @param array<string, Item> $items
     * @param bool $all Whether to include manual targets
     *
     * @return array<string, TestResult>
     * @throws InvalidInputException When a runner uses an unknown extension
     */
    public function run(Project $project, array $items, bool $all): array
    {
        $registry = new Registry($project->runnerExtensions);
        $results = [];
        foreach ($items as $item) {
            if ($item->kind !== 'specification' || $item->status !== 'supported') {
                continue;
            }
            foreach ($item->tests as $test) {
                if (!$all && $test->run === 'manual') {
                    continue;
                }
                $key = $test->runner . "\0" . $test->target;
                $config = $project->runners[$test->runner];
                $results[$key] ??= $registry->get($config->extension)->run($config, $test->target);
            }
        }
        return $results;
    }
}
