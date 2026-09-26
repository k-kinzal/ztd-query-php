<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Api\Execution\ResourceLimits
 */
#[CoversClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[Small]
final class ResourceLimitsTest extends TestCase
{
    public function testInvalidMemoryLimitsAreRejectedBeforeAnalysis(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        new \Deriver\Api\Execution\ResourceLimits(memoryBytes:1);
    }
    public function testNonfiniteDurationsAreRejectedBeforeAnalysis(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        new \Deriver\Api\Execution\ResourceLimits(seconds:INF);
    }
}
