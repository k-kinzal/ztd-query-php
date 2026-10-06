<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\Evaluation\Control\Resources;
use Deriver\Query\CancellationToken;
use Deriver\Query\ResourceLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Control\Resources
 */
#[CoversClass(Resources::class)]
#[UsesClass(CancellationToken::class)]
#[UsesClass(ResourceLimits::class)]
#[Small]
final class ResourcesTest extends TestCase
{
    public function testReasonObservesCancellationAndKeepsItsFirstCause(): void
    {
        $token = new CancellationToken();
        $resources = new Resources(new ResourceLimits(cancellation:$token));
        $initial = $resources->reason();
        $token->cancel();
        $first = $resources->reason();
        $second = $resources->reason();
        self::assertSame([null, 'CANCELLED', 'CANCELLED'], [$initial, $first, $second]);
    }
    public function testReasonDetectsAnExpiredMonotonicDeadline(): void
    {
        $resources = new Resources(new ResourceLimits(seconds:0.000000001));
        self::assertSame('TIME_LIMIT', $resources->reason());
    }
    public function testReasonReservesMemoryBeforeStoppingTheComputation(): void
    {
        $resources = new Resources(new ResourceLimits(memoryBytes:1048576));
        $allocated = str_repeat('x', 1048576);
        self::assertSame('MEMORY_LIMIT', $resources->reason());
        self::assertSame(1048576, strlen($allocated));
    }
    public function testMemoryLimitParsesSuffixesAndBoundsOverflow(): void
    {
        self::assertNull(Resources::memoryLimit('-1'));
        self::assertSame(131072, Resources::memoryLimit('128K'));
        self::assertSame(134217728, Resources::memoryLimit('128M'));
        self::assertSame(1073741824, Resources::memoryLimit('1G'));
        self::assertSame(PHP_INT_MAX, Resources::memoryLimit('999999999999999999999G'));
    }
    public function testReasonRejectsAnticipatedAllocationBeforeItOccurs(): void
    {
        $resources = new Resources(new ResourceLimits(memoryBytes: 1048576));
        self::assertSame('MEMORY_LIMIT', $resources->reason(1048576));
        self::assertSame('MEMORY_LIMIT', $resources->reason());
    }
    public function testStackLimitReservesDebuggerHeadroomWithoutConstrainingDisabledInstrumentation(): void
    {
        self::assertSame(448, Resources::stackLimit(2048, '512', 'develop'));
        self::assertSame(2048, Resources::stackLimit(2048, '512', 'off'));
        self::assertSame(2048, Resources::stackLimit(2048, false, false));
        self::assertSame(64, Resources::stackLimit(64, '512', 'coverage'));
    }

    /**
     * @param string $setting Host allocator setting
     * @param int|null $expected Byte count or unlimited/invalid setting
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMemorySettings')]
    public function testMemoryLimitAcceptsByteCountsAndWhitespaceWithoutOverflow(string $setting, ?int $expected): void
    {
        self::assertSame($expected, Resources::memoryLimit($setting));
    }

    /**
     * @return iterable<string,array{string,int|null}>
     */
    public static function providerMemorySettings(): iterable
    {
        yield 'bytes' => ['123456', 123456];
        yield 'zero bytes' => ['0', 0];
        yield 'lowercase kilo' => ['2k', 2048];
        yield 'lowercase mega' => ['2m', 2097152];
        yield 'lowercase giga' => ['2g', 2147483648];
        yield 'trim whitespace' => ["  128 k\n", 131072];
        yield 'bytes maximum' => ['9223372036854775807', PHP_INT_MAX];
        yield 'largest kilo product' => ['9007199254740991K', 9223372036854774784];
        yield 'overflow kilo product' => ['9007199254740992K', PHP_INT_MAX];
        yield 'empty' => ['', null];
        yield 'negative' => ['-2', null];
        yield 'decimal' => ['1.5G', null];
        yield 'unknown suffix' => ['128T', null];
        yield 'extra suffix' => ['128MB', null];
        yield 'embedded whitespace' => ['1 28M', null];
    }

    /**
     * @param int $configured Requested frame budget
     * @param string|false $limit Debugger nesting limit
     * @param string|false $mode Debugger mode
     * @param int $expected Effective frame limit
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDebuggerLimits')]
    public function testStackLimitKeepsTerminationHeadroomAcrossDebuggerModes(int $configured, string|false $limit, string|false $mode, int $expected): void
    {
        self::assertSame($expected, Resources::stackLimit($configured, $limit, $mode));
    }

    /**
     * @return iterable<string,array{int,string|false,string|false,int}>
     */
    public static function providerDebuggerLimits(): iterable
    {
        yield 'absent mode' => [2048, '512', false, 2048];
        yield 'empty mode' => [2048, '512', '', 2048];
        yield 'disabled mode' => [2048, '512', 'off', 2048];
        yield 'missing limit' => [2048, false, 'develop', 2048];
        yield 'zero unlimited' => [2048, '0', 'develop', 2048];
        yield 'negative limit' => [2048, '-1', 'develop', 2048];
        yield 'nonintegral limit' => [2048, '512.0', 'develop', 2048];
        yield 'nondecimal limit' => [2048, '5e2', 'develop', 2048];
        yield 'one frame' => [2048, '1', 'develop', 1];
        yield 'small debugger limit' => [2048, '64', 'develop', 1];
        yield 'one frame headroom' => [2048, '65', 'develop', 1];
        yield 'two frames headroom' => [2048, '66', 'develop', 2];
        yield 'mixed instrumentation modes' => [2048, '512', 'develop,coverage', 448];
        yield 'configured bound is tighter' => [100, '512', 'coverage', 100];
    }

    public function testReasonAllowsWorkBeforeTheDeadlineAndBelowTheMemoryLimit(): void
    {
        $resources = new Resources(new ResourceLimits(memoryBytes:1048576, seconds:3600));
        self::assertNull($resources->reason());
        self::assertNull($resources->reason(65536));
    }

    public function testReasonKeepsAnExpiredDeadlineAfterLaterCancellation(): void
    {
        $token = new CancellationToken();
        $resources = new Resources(new ResourceLimits(seconds:0.000000001, cancellation:$token));
        $first = $resources->reason();
        $token->cancel();
        $second = $resources->reason();
        self::assertSame(['TIME_LIMIT', 'TIME_LIMIT'], [$first, $second]);
    }

    public function testReasonChecksTheStackOnlyBeforeEnteringAnotherCallable(): void
    {
        $resources = new Resources(new ResourceLimits(stackFrames:64));
        self::assertNull(\Tests\Fake\HostStackFixture::descend($resources, 80, false));
        self::assertSame('STACK_LIMIT', \Tests\Fake\HostStackFixture::descend($resources, 80, true));
    }

    public function testReasonRefusesOnlyTheDeepCallAndAdmitsLaterShallowCalls(): void
    {
        $resources = new Resources(new ResourceLimits(stackFrames:64));
        self::assertSame('STACK_LIMIT', \Tests\Fake\HostStackFixture::descend($resources, 80, true));
        self::assertSame([null, null], [$resources->reason(), $resources->reason(call:true)]);
    }

    public function testReasonCountsOnlyFramesAddedAfterTheQueryStarts(): void
    {
        $resources = \Tests\Fake\HostStackFixture::open(new ResourceLimits(stackFrames:64), 80);
        self::assertNull(\Tests\Fake\HostStackFixture::descend($resources, 40, true));
    }

    public function testReasonKeepsPermanentInterruptionsAfterAStackRefusal(): void
    {
        $token = new CancellationToken();
        $resources = new Resources(new ResourceLimits(stackFrames:64, cancellation:$token));
        self::assertSame('STACK_LIMIT', \Tests\Fake\HostStackFixture::descend($resources, 80, true));
        $token->cancel();
        self::assertSame(['CANCELLED', 'CANCELLED'], [$resources->reason(), $resources->reason(call:true)]);
    }

    public function testPermanentIgnoresTheHostStack(): void
    {
        $resources = new Resources(new ResourceLimits(stackFrames:64));
        self::assertNull(\Tests\Fake\HostStackFixture::descend($resources, 80, false));
        self::assertNull($resources->permanent(0));
        self::assertSame('MEMORY_LIMIT', $resources->permanent(PHP_INT_MAX));
    }

    public function testReasonReleasesTerminationMemoryWhenCancelled(): void
    {
        $token = new CancellationToken();
        $resources = new Resources(new ResourceLimits(cancellation:$token));
        $before = memory_get_usage();
        $token->cancel();
        self::assertSame('CANCELLED', $resources->reason());
        self::assertGreaterThan(131072, $before - memory_get_usage());
    }
}
