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

        self::assertSame(['id' => 8], $allocator->allocateMissing('users', $strategies, ['name'], [['id' => 7]]));
        self::assertSame(['id' => 9], $allocator->allocateMissing('users', $strategies, ['name'], []));
    }

    public function testSequenceIdentityDoesNotUseExplicitRowMaximum(): void
    {
        $allocator = new ShadowIdentityAllocator();
        $strategies = ['id' => IdentityGenerationStrategy::Sequence];

        self::assertSame(['id' => 1], $allocator->allocateMissing('users', $strategies, [], [['id' => 99]]));
        self::assertSame(['id' => 2], $allocator->allocateMissing('users', $strategies, [], []));
        self::assertSame([], $allocator->allocateMissing('users', $strategies, ['id'], []));
    }

    public function testGeneratedStateIsSeparatedByTableAndColumn(): void
    {
        $allocator = new ShadowIdentityAllocator();

        self::assertSame(
            ['id' => 1, 'tenant_id' => 1],
            $allocator->allocateMissing('users', [
                'id' => IdentityGenerationStrategy::Sequence,
                'tenant_id' => IdentityGenerationStrategy::Sequence,
            ], [], []),
        );
        self::assertSame(
            ['id' => 1],
            $allocator->allocateMissing('orders', ['id' => IdentityGenerationStrategy::Sequence], [], []),
        );
        self::assertSame(
            ['id' => 2, 'tenant_id' => 2],
            $allocator->allocateMissing('users', [
                'id' => IdentityGenerationStrategy::Sequence,
                'tenant_id' => IdentityGenerationStrategy::Sequence,
            ], [], []),
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
            ['tenant_id' => 1],
            $allocator->allocateMissing('users', $strategies, ['id'], []),
        );
        self::assertSame(
            ['id' => 1, 'tenant_id' => 2],
            $allocator->allocateMissing('users', $strategies, [], []),
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
            ['id' => 16],
            $allocator->allocateMissing('users', ['id' => IdentityGenerationStrategy::MaxValue], [], $rows),
        );
        self::assertSame(
            ['id' => 1],
            (new ShadowIdentityAllocator())->allocateMissing(
                'empty',
                ['id' => IdentityGenerationStrategy::MaxValue],
                [],
                [['id' => 'invalid'], ['id' => 0.9]],
            ),
        );
        self::assertSame(
            ['id' => 13],
            (new ShadowIdentityAllocator())->allocateMissing(
                'strings',
                ['id' => IdentityGenerationStrategy::MaxValue],
                [],
                [['id' => '12']],
            ),
        );
        self::assertSame(
            ['id' => 1],
            (new ShadowIdentityAllocator())->allocateMissing(
                'non-integers',
                ['id' => IdentityGenerationStrategy::MaxValue],
                [],
                [['id' => true], ['id' => 18.0]],
            ),
        );
    }

    public function testSelectAllocationContinuesFromMaterializedRows(): void
    {
        $allocator = new ShadowIdentityAllocator();
        $strategies = ['id' => IdentityGenerationStrategy::Sequence];

        self::assertSame(
            ['id' => 1],
            $allocator->allocateSelectStarts('users', $strategies, [], []),
        );
        self::assertSame(
            ['id' => 3],
            $allocator->allocateMissing('users', $strategies, ['name'], [['id' => 1], ['id' => 2]]),
        );
    }

    public function testSelectAllocationUsesStrategyStateAndAllColumns(): void
    {
        $allocator = new ShadowIdentityAllocator();

        self::assertSame(
            [
                'max_id' => 8,
                'sequence_id' => 1,
            ],
            $allocator->allocateSelectStarts(
                'users',
                [
                    'max_id' => IdentityGenerationStrategy::MaxValue,
                    'sequence_id' => IdentityGenerationStrategy::Sequence,
                ],
                [],
                [['max_id' => 7, 'sequence_id' => 99]],
            ),
        );
        self::assertSame(
            ['sequence_id' => 100],
            $allocator->allocateMissing(
                'users',
                ['sequence_id' => IdentityGenerationStrategy::Sequence],
                [],
                [['sequence_id' => 99]],
            ),
        );
    }

    public function testSelectAllocationSkipsProvidedIdentityColumns(): void
    {
        self::assertSame(
            ['generated_id' => 1],
            (new ShadowIdentityAllocator())->allocateSelectStarts(
                'users',
                [
                    'provided_id' => IdentityGenerationStrategy::Sequence,
                    'generated_id' => IdentityGenerationStrategy::Sequence,
                ],
                ['provided_id'],
                [],
            ),
        );
    }

    public function testCommitProjectionBeginProjectionUncommittedPreparationDoesNotConsumeIdentity(): void
    {
        $allocator = new ShadowIdentityAllocator();
        $strategies = ['id' => IdentityGenerationStrategy::Sequence];

        $allocator->beginProjection();
        self::assertSame(['id' => 1], $allocator->allocateMissing('users', $strategies, ['name'], []));
        $allocator->beginProjection();
        self::assertSame(['id' => 1], $allocator->allocateMissing('users', $strategies, ['name'], []));
        $allocator->commitProjection();
        $allocator->beginProjection();
        self::assertSame(['id' => 2], $allocator->allocateMissing('users', $strategies, ['name'], [['id' => 1]]));
    }

    public function testBeginProjectionStartsFromWhatWasCommitted(): void
    {
        $allocator = new ShadowIdentityAllocator();
        $strategies = ['id' => IdentityGenerationStrategy::Sequence];
        $allocator->beginProjection();
        $allocator->allocateMissing('order', $strategies, [], []);
        $allocator->commitProjection();

        $allocator->beginProjection();

        self::assertSame(['id' => 2], $allocator->allocateMissing('order', $strategies, [], []));
    }

    public function testBeginProjectionDiscardsWhatAProjectionNeverCommitted(): void
    {
        $allocator = new ShadowIdentityAllocator();
        $strategies = ['id' => IdentityGenerationStrategy::Sequence];
        $allocator->beginProjection();
        $allocator->allocateMissing('order', $strategies, [], []);

        $allocator->beginProjection();

        self::assertSame(['id' => 1], $allocator->allocateMissing('order', $strategies, [], []));
    }

    public function testAllocateMissingLeavesAColumnTheStatementWroteAlone(): void
    {
        $allocator = new ShadowIdentityAllocator();
        $strategies = ['id' => IdentityGenerationStrategy::Sequence];

        self::assertSame([], $allocator->allocateMissing('order', $strategies, ['id'], []));
    }

    public function testAllocateSelectStartsAnswersTheFirstNumberTheQueryWouldTake(): void
    {
        $allocator = new ShadowIdentityAllocator();
        $strategies = ['id' => IdentityGenerationStrategy::MaxValue];

        self::assertSame(
            ['id' => 4],
            $allocator->allocateSelectStarts('order', $strategies, [], [['id' => 3]]),
        );
    }

    public function testAllocateSelectStartsLeavesAColumnTheStatementWroteAlone(): void
    {
        $allocator = new ShadowIdentityAllocator();
        $strategies = ['id' => IdentityGenerationStrategy::Sequence];

        self::assertSame([], $allocator->allocateSelectStarts('order', $strategies, ['id'], []));
    }

    public function testNextAfterExistingRowsGoesPastTheLargestNumberAlreadyThere(): void
    {
        self::assertSame(8, (new ShadowIdentityAllocator())->nextAfterExistingRows(
            'id',
            [['id' => 3], ['id' => 7], ['id' => 5]],
            1,
        ));
    }

    public function testNextAfterExistingRowsKeepsTheNumberItWasGivenWhereNothingIsLarger(): void
    {
        self::assertSame(9, (new ShadowIdentityAllocator())->nextAfterExistingRows('id', [['id' => 3]], 9));
    }

    public function testIntegerValueReadsANumberHoweverTheDriverSpelledIt(): void
    {
        $allocator = new ShadowIdentityAllocator();

        self::assertSame(7, $allocator->integerValue(7));
        self::assertSame(7, $allocator->integerValue('7'));
    }

    public function testIntegerValueIsNothingForAValueThatIsNotAWholeNumber(): void
    {
        $allocator = new ShadowIdentityAllocator();

        self::assertNull($allocator->integerValue('seven'));
        self::assertNull($allocator->integerValue(null));
        self::assertNull($allocator->integerValue(1.5));
    }
    public function testNextValueReadsAMaxValueColumnOffTheRowsEveryTime(): void
    {
        $allocator = new ShadowIdentityAllocator();

        self::assertSame(
            8,
            $allocator->nextValue('users', 'id', IdentityGenerationStrategy::MaxValue, [['id' => 7]]),
        );
    }

    public function testNextValueStartsASequenceAtOneHoweverManyRowsAreThere(): void
    {
        $allocator = new ShadowIdentityAllocator();

        self::assertSame(
            1,
            $allocator->nextValue('users', 'id', IdentityGenerationStrategy::Sequence, [['id' => 7]]),
        );
    }

}
