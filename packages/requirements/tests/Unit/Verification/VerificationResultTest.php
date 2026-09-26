<?php

declare(strict_types=1);

namespace Tests\Unit\Verification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Verification\VerificationResult;

#[CoversClass(VerificationResult::class)]
#[Small]
final class VerificationResultTest extends TestCase
{
    public function testToArrayReportsEveryField(): void
    {
        self::assertSame(['status' => 'failed', 'tests' => 3, 'passed_targets' => 1, 'total_targets' => 2, 'message' => 'b: Expected failure.', 'deferred_targets' => 0], (new VerificationResult('failed', 3, 1, 2, 'b: Expected failure.'))->toArray());
    }

    public function testToArrayKeepsANullPassedTargetCountAndEmptyMessage(): void
    {
        self::assertSame(['status' => 'not-run', 'tests' => 0, 'passed_targets' => null, 'total_targets' => 2, 'message' => '', 'deferred_targets' => 0], (new VerificationResult('not-run', 0, null, 2))->toArray());
    }
    public function testToArrayReportsDeferredTargetsSeparatelyFromPassingTargets(): void
    {
        self::assertSame(['status' => 'deferred', 'tests' => 1, 'passed_targets' => 1, 'total_targets' => 2, 'message' => 'Deferred.', 'deferred_targets' => 1], (new VerificationResult('deferred', 1, 1, 2, 'Deferred.', 1))->toArray());
    }
}
