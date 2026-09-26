<?php

declare(strict_types=1);

namespace Requirements\Verification;

use Requirements\Input\InvalidInputException;
use Requirements\Model\Item;
use Requirements\Model\Project;

/**
 * Runs the tests linked to specifications and decides each verdict.
 *
 * Each distinct runner and target pair runs once and its result is shared. A specification
 * passes when every linked target passes with at least one executed test; requirements are
 * not applicable, unsupported specifications are not run.
 */
final class Verifier
{
    /**
     * Verifies items.
     *
     * @param Project $project The loaded project with its runners
     * @param array<string, Item> $items The items to verify by ID
     * @param bool $noTest Whether to report linked targets without running them
     * @param bool $all Whether to include manual tests
     *
     * @return array<string, VerificationResult> The verdicts by item ID
     *
     * @throws InvalidInputException When a runner uses an unknown extension
     */
    public function verify(Project $project, array $items, bool $noTest = false, bool $all = false): array
    {
        $cache = $noTest ? [] : (new TestExecution())->run($project, $items, $all);
        $results = [];
        foreach ($items as $item) {
            $targets = [];
            foreach ($item->tests as $test) {
                $targets[$test->runner . "\0" . $test->target] = $test;
            }
            $total = count($targets);
            if ($item->kind !== 'specification') {
                $results[$item->id] = new VerificationResult('not-applicable', 0, null, $total);
                continue;
            }
            if ($item->status === 'unsupported') {
                $results[$item->id] = new VerificationResult('unsupported', 0, null, $total);
                continue;
            }
            if ($noTest) {
                $results[$item->id] = new VerificationResult('not-run', 0, null, $total);
                continue;
            }
            if ($item->tests === []) {
                $results[$item->id] = new VerificationResult('unverified', 0, 0, 0, 'No tests linked.');
                continue;
            }
            $results[$item->id] = (new TargetResults())->summarize($targets, $cache);
        }
        return $results;
    }
}
