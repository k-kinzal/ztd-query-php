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

    public function testGeneratedStateIsSeparatedByTableAndColumn(): void
    {
        $allocator = new ShadowIdentityAllocator();

        self::assertSame(
            ['id' => '1', 'tenant_id' => '1'],
            $allocator->allocateMissing('users', [
                'id' => IdentityGenerationStrategy::Sequence,
                'tenant_id' => IdentityGenerationStrategy::Sequence,
            ], [], [], []),
        );
        self::assertSame(
            ['id' => '1'],
            $allocator->allocateMissing('orders', ['id' => IdentityGenerationStrategy::Sequence], [], [], []),
        );
        self::assertSame(
            ['id' => '2', 'tenant_id' => '2'],
            $allocator->allocateMissing('users', [
                'id' => IdentityGenerationStrategy::Sequence,
                'tenant_id' => IdentityGenerationStrategy::Sequence,
            ], [], [], []),
        );
    }

    public function testExplicitIdentityDoesNotPreventOtherIdentitiesFromBeingAllocated(): void
    {
        $allocator = new ShadowIdentityAllocator();
        $strategies = [
            'id' => IdentityGenerationStrategy::Sequence,
            'tenant_id' => IdentityGenerationStrategy::Sequence,
        ];

        self::assertSame(
            ['tenant_id' => '1'],
            $allocator->allocateMissing('users', $strategies, ['id'], ['100'], []),
        );
        self::assertSame(
            ['id' => '1', 'tenant_id' => '2'],
            $allocator->allocateMissing('users', $strategies, ['id'], ['  default  '], []),
        );
    }

    public function testMaxValueUsesOnlyIntegerRows(): void
    {
        $allocator = new ShadowIdentityAllocator();
        $rows = [
            ['id' => null],
            ['id' => 'prefix99'],
            ['id' => '20suffix'],
            ['id' => 18.9],
            ['id' => false],
            ['id' => '-4'],
            ['id' => '12'],
            ['id' => 15],
        ];

        self::assertSame(
            ['id' => '16'],
            $allocator->allocateMissing('users', ['id' => IdentityGenerationStrategy::MaxValue], [], [], $rows),
        );
        self::assertSame(
            ['id' => '1'],
            (new ShadowIdentityAllocator())->allocateMissing(
                'empty',
                ['id' => IdentityGenerationStrategy::MaxValue],
                [],
                [],
                [['id' => 'invalid'], ['id' => 0.9]],
            ),
        );
        self::assertSame(
            ['id' => '13'],
            (new ShadowIdentityAllocator())->allocateMissing(
                'strings',
                ['id' => IdentityGenerationStrategy::MaxValue],
                [],
                [],
                [['id' => '12']],
            ),
        );
        self::assertSame(
            ['id' => '1'],
            (new ShadowIdentityAllocator())->allocateMissing(
                'non-integers',
                ['id' => IdentityGenerationStrategy::MaxValue],
                [],
                [],
                [['id' => true], ['id' => 18.0]],
            ),
        );
    }
}
