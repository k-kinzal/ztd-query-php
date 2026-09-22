<?php

declare(strict_types=1);

namespace Requirements\Test;

use Requirements\Model\Item;
use Requirements\Model\Project;

final class Verifier
{
    /**
     * @param array<string, Item> $items
     * @return array<string, TestResult>
     */
    public function verify(Project $project, array $items): array
    {
        $registry = new Registry($project->runnerExtensions);
        $cache = [];
        $results = [];
        foreach ($items as $item) {
            if ($item->kind !== 'specification') {
                continue;
            }
            if ($item->status === 'unsupported') {
                $results[$item->id] = new TestResult('unsupported', 0, $item->reason);
                continue;
            }
            if ($item->tests === []) {
                $results[$item->id] = new TestResult('unverified', 0, 'No tests linked.');
                continue;
            }
            $count = 0;
            $messages = [];
            $status = 'passed';
            foreach ($item->tests as $test) {
                $key = $test->runner . "\0" . $test->target;
                $config = $project->runners[$test->runner];
                $result = $cache[$key] ??= $registry->get($config->extension)->run($config, $test->target);
                $count += $result->tests;
                if ($result->status !== 'passed' || $result->tests < 1) {
                    $status = 'failed';
                    $messages[] = "$test->target: " . $result->message;
                }
            }
            $results[$item->id] = new TestResult($status, $count, implode("\n", $messages));
        }
        return $results;
    }
}
