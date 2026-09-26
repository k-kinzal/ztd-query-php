<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Control;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Control\Resources
 */
#[CoversClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Api\Execution\CancellationToken::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[Small]
final class ResourcesTest extends TestCase
{
    public function testReasonObservesCancellationAndKeepsItsFirstCause(): void
    {
        $token = new \Deriver\Api\Execution\CancellationToken();
        $resources = new \Deriver\Internal\Solver\Control\Resources(new \Deriver\Api\Execution\ResourceLimits(cancellation:$token));
        $initial = $resources->reason();
        $token->cancel();
        $first = $resources->reason();
        $second = $resources->reason();
        self::assertSame([null, 'CANCELLED', 'CANCELLED'], [$initial, $first, $second]);
    }
    public function testReasonDetectsAnExpiredMonotonicDeadline(): void
    {
        $resources = new \Deriver\Internal\Solver\Control\Resources(new \Deriver\Api\Execution\ResourceLimits(seconds:0.000000001));
        self::assertSame('TIME_LIMIT', $resources->reason());
    }
    public function testReasonReservesMemoryBeforeStoppingTheComputation(): void
    {
        $resources = new \Deriver\Internal\Solver\Control\Resources(new \Deriver\Api\Execution\ResourceLimits(memoryBytes:1048576));
        $allocated = str_repeat('x', 1048576);
        self::assertSame('MEMORY_LIMIT', $resources->reason());
        self::assertSame(1048576, strlen($allocated));
    }
    public function testMemoryLimitParsesSuffixesAndBoundsOverflow(): void
    {
        self::assertNull(\Deriver\Internal\Solver\Control\Resources::memoryLimit('-1'));
        self::assertSame(131072, \Deriver\Internal\Solver\Control\Resources::memoryLimit('128K'));
        self::assertSame(134217728, \Deriver\Internal\Solver\Control\Resources::memoryLimit('128M'));
        self::assertSame(1073741824, \Deriver\Internal\Solver\Control\Resources::memoryLimit('1G'));
        self::assertSame(PHP_INT_MAX, \Deriver\Internal\Solver\Control\Resources::memoryLimit('999999999999999999999G'));
    }
    public function testReasonRejectsAnticipatedAllocationBeforeItOccurs(): void
    {
        $resources = new \Deriver\Internal\Solver\Control\Resources(new \Deriver\Api\Execution\ResourceLimits(memoryBytes: 1048576));
        self::assertSame('MEMORY_LIMIT', $resources->reason(1048576));
        self::assertSame('MEMORY_LIMIT', $resources->reason());
    }
    public function testStackLimitReservesDebuggerHeadroomWithoutConstrainingDisabledInstrumentation(): void
    {
        self::assertSame(448, \Deriver\Internal\Solver\Control\Resources::stackLimit(2048, '512', 'develop'));
        self::assertSame(2048, \Deriver\Internal\Solver\Control\Resources::stackLimit(2048, '512', 'off'));
        self::assertSame(2048, \Deriver\Internal\Solver\Control\Resources::stackLimit(2048, false, false));
        self::assertSame(64, \Deriver\Internal\Solver\Control\Resources::stackLimit(64, '512', 'coverage'));
    }
}
