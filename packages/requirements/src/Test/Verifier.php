<?php

declare(strict_types=1);

namespace Requirements\Test;

use Requirements\Model\Item;
use Requirements\Model\Project;

final class Verifier
{
    /**
     * @param array<string, Item> $items
     * @return array<string, VerificationResult>
     */
    public function verify(Project $project, array $items, bool $noTest = false): array
    {
        $registry = new Registry($project->runnerExtensions);
        $cache = [];
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
            $count = 0;
            $passed = 0;
            $messages = [];
            $status = 'passed';
            foreach ($targets as $key => $test) {
                $config = $project->runners[$test->runner];
                $result = $cache[$key] ??= $registry->get($config->extension)->run($config, $test->target);
                $count += $result->tests;
                if ($result->status !== 'passed' || $result->tests < 1) {
                    $status = 'failed';
                    $messages[] = "$test->target: " . $result->message;
                } else {
                    ++$passed;
                }
            }
            $results[$item->id] = new VerificationResult($status, $count, $passed, $total, implode("\n", $messages));
        }
        return $results;
    }
}
