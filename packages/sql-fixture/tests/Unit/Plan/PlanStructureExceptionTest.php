<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\PlanStructureException;

#[CoversClass(PlanStructureException::class)]
#[UsesClass(ColumnRef::class)]
final class PlanStructureExceptionTest extends TestCase
{
    #[Test]
    public function columnsBoundTwiceNamesTheColumnAndBothParents(): void
    {
        $message = PlanStructureException::columnsBoundTwice(
            ColumnRef::of('b', 'x'),
            ColumnRef::of('a', 'id'),
            ColumnRef::of('c', 'id')
        )->getMessage();

        self::assertStringContainsString('b.x is bound to a.id and to c.id', $message);
        self::assertStringContainsString('can reference one parent', $message);
    }

    #[Test]
    public function cycleShowsTheLoopClosingBackOnItself(): void
    {
        $message = PlanStructureException::cycle(['a', 'b', 'c'])->getMessage();

        self::assertStringContainsString('a -> b -> c -> a', $message);
    }

    #[Test]
    public function unboundedSelfReferenceSuggestsTheOptionalMarker(): void
    {
        $message = PlanStructureException::unboundedSelfReference(
            'category',
            'category.id < category.parent_id'
        )->getMessage();

        self::assertStringContainsString('category.id < category.parent_id', $message);
        self::assertStringContainsString('Mark the child optional with ?', $message);
    }

    #[Test]
    public function isLogicException(): void
    {
        self::assertInstanceOf(\LogicException::class, PlanStructureException::cycle(['a', 'b']));
    }
}
