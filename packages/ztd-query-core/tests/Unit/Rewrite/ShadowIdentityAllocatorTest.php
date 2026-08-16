<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\ShadowIdentityAllocator;
use ZtdQuery\Schema\IdentityGenerationStrategy;

#[CoversClass(ShadowIdentityAllocator::class)]
final class ShadowIdentityAllocatorTest extends TestCase
{
    public function testMaxValueIdentityAdvancesPastRowsAndSurvivesDeletion(): void
    {
        $allocator = new ShadowIdentityAllocator();
        $strategies = ['id' => IdentityGenerationStrategy::MaxValue];

        self::assertSame(['id' => '8'], $allocator->allocateMissing('users', $strategies, ['name'], ["'a'"], [['id' => 7]]));
        self::assertSame(['id' => '9'], $allocator->allocateMissing('users', $strategies, ['name'], ["'b'"], []));
    }

    public function testSequenceIdentityDoesNotUseExplicitRowMaximum(): void
    {
        $allocator = new ShadowIdentityAllocator();
        $strategies = ['id' => IdentityGenerationStrategy::Sequence];

        self::assertSame(['id' => '1'], $allocator->allocateMissing('users', $strategies, [], [], [['id' => 99]]));
        self::assertSame(['id' => '2'], $allocator->allocateMissing('users', $strategies, ['id'], ['DEFAULT'], []));
        self::assertSame([], $allocator->allocateMissing('users', $strategies, ['id'], ['500'], []));
    }
}
