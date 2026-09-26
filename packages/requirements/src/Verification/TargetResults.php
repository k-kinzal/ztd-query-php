<?php

declare(strict_types=1);

namespace Requirements\Verification;

use Requirements\Model\TestReference;
use Requirements\Test\TestResult;

/**
 * Summarizes executed and deferred targets without treating deferred work as a pass.
 */
final class TargetResults
{
    /**
     * Combines one specification's target results.
     *
     * @param array<string, TestReference> $targets
     * @param array<string, TestResult> $cache
     *
     * @return VerificationResult The verdict and passing and deferred counts
     */
    public function summarize(array $targets, array $cache): VerificationResult
    {
        $count = 0;
        $passed = 0;
        $deferred = 0;
        $messages = [];
        $status = 'passed';
        foreach ($targets as $key => $test) {
            if (!isset($cache[$key])) {
                ++$deferred;
                $messages[] = "$test->target: Deferred; use --all to run manual tests.";
                continue;
            }
            $result = $cache[$key];
            $count += $result->tests;
            if ($result->status !== 'passed' || $result->tests < 1) {
                $status = 'failed';
                $messages[] = "$test->target: " . $result->message;
            } else {
                ++$passed;
            }
        }
        if ($status === 'passed' && $deferred > 0) {
            $status = 'deferred';
        }
        return new VerificationResult($status, $count, $passed, count($targets), implode("\n", $messages), $deferred);
    }

}
