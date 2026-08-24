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

        self::assertSame(
            'b.x is bound to a.id and to c.id. A column can reference one parent, so one of '
            . 'the two relations has to go.',
            $message
        );
    }

    #[Test]
    public function cycleShowsTheLoopClosingBackOnItself(): void
    {
        $message = PlanStructureException::cycle(['a', 'b', 'c'])->getMessage();

        self::assertSame(
            'The relations form a cycle: a -> b -> c -> a. Each table would have to be '
            . 'generated before itself, so there is no order that satisfies them.',
            $message
        );
    }

    #[Test]
    public function unboundedSelfReferenceSuggestsTheOptionalMarker(): void
    {
        $message = PlanStructureException::unboundedSelfReference(
            'category',
            'category.id < category.parent_id'
        )->getMessage();

        self::assertSame(
            'The relation category.id < category.parent_id makes every category row need '
            . 'another one, without end. Mark the child optional with ? so the chain can stop.',
            $message
        );
    }

    #[Test]
    public function isLogicException(): void
    {
        self::assertInstanceOf(\LogicException::class, PlanStructureException::cycle(['a', 'b']));
    }
}
