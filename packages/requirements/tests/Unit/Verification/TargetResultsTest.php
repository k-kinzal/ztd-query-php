<?php

declare(strict_types=1);

namespace Tests\Unit\Verification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Model\TestReference;
use Requirements\Test\TestResult;
use Requirements\Verification\TargetResults;
use Requirements\Verification\VerificationResult;

#[CoversClass(TargetResults::class)]
#[UsesClass(TestReference::class)]
#[UsesClass(TestResult::class)]
#[UsesClass(VerificationResult::class)]
#[Small]
final class TargetResultsTest extends TestCase
{
    /**
     * @param array<string, TestResult> $cache
     */
    #[DataProvider('providerResults')]
    public function testSummarizeDistinguishesFailureDeferralAndExecutedCases(array $cache, string $status, int $tests, int $passed, int $deferred): void
    {
        $targets = ["unit\0fast" => new TestReference('unit', 'fast'), "unit\0slow" => new TestReference('unit', 'slow', 'manual')];
        $result = (new TargetResults())->summarize($targets, $cache);
        self::assertSame($status, $result->status);
        self::assertSame($tests, $result->tests);
        self::assertSame($passed, $result->passedTargets);
        self::assertSame($deferred, $result->deferredTargets);
        self::assertSame(2, $result->totalTargets);
        self::assertSame($deferred > 0, str_contains($result->message, 'use --all'));
    }

    /**
     * @return array<string, array{array<string, TestResult>, string, int, int, int}>
     */
    public static function providerResults(): array
    {
        return [
            'all deferred' => [[], 'deferred', 0, 0, 2],
            'partly deferred' => [["unit\0fast" => new TestResult('passed', 2)], 'deferred', 2, 1, 1],
            'failure with deferral' => [["unit\0fast" => new TestResult('failed', 1, 'Assertion failed.')], 'failed', 1, 0, 1],
            'zero executed cases' => [["unit\0fast" => new TestResult('passed', 0)], 'failed', 0, 0, 1],
            'all passed' => [["unit\0fast" => new TestResult('passed', 2), "unit\0slow" => new TestResult('passed', 3)], 'passed', 5, 2, 0],
            'failure without deferral' => [["unit\0fast" => new TestResult('passed', 2), "unit\0slow" => new TestResult('error', 0, 'Cannot run.')], 'failed', 2, 1, 0],
        ];
    }
}
