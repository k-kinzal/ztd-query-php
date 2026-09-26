<?php

declare(strict_types=1);

namespace Tests\Unit\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Test\TestResult;

#[CoversClass(TestResult::class)]
#[Small]
final class TestResultTest extends TestCase
{
    public function testToArrayReportsEveryField(): void
    {
        self::assertSame(['status' => 'failed', 'tests' => 3, 'message' => 'Expected failure.'], (new TestResult('failed', 3, 'Expected failure.'))->toArray());
    }

    public function testToArrayDefaultsTheMessageToEmpty(): void
    {
        self::assertSame(['status' => 'passed', 'tests' => 2, 'message' => ''], (new TestResult('passed', 2))->toArray());
    }
}
