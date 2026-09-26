<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Api\Execution\ResourceLimits
 */
#[CoversClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\InvalidInputException::class)]
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

    public function testDefaultsProvideTheDocumentedResourceAllowance(): void
    {
        $limits = new \Deriver\Api\Execution\ResourceLimits();
        self::assertSame(256 * 1024 * 1024, $limits->memoryBytes);
        self::assertSame(0.0, $limits->seconds);
        self::assertSame(2048, $limits->stackFrames);
        self::assertNull($limits->cancellation);
    }

    public function testMinimumAcceptedLimitsRemainUsable(): void
    {
        $limits = new \Deriver\Api\Execution\ResourceLimits(memoryBytes: 1048576, seconds: 0.0, stackFrames: 64);
        self::assertSame(1048576, $limits->memoryBytes);
        self::assertSame(0.0, $limits->seconds);
        self::assertSame(64, $limits->stackFrames);
    }

    public function testFinitePositiveDurationsRemainEnabled(): void
    {
        self::assertSame(0.25, (new \Deriver\Api\Execution\ResourceLimits(seconds: 0.25))->seconds);
    }

    /**
     * @param int $memory
     * @param float $seconds
     * @param int $frames
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerRejectedBounds')]
    public function testInvalidBoundsFailBeforeStartingAQuery(int $memory, float $seconds, int $frames): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        new \Deriver\Api\Execution\ResourceLimits($memory, $seconds, stackFrames: $frames);
    }

    /**
     * @return list<array{int, float, int}>
     */
    public static function providerRejectedBounds(): array
    {
        return [[1048575, 0.0, 64], [1048576, -0.01, 64], [1048576, NAN, 64], [1048576, -INF, 64], [1048576, 0.0, 63]];
    }
}
