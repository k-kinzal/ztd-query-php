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
#[UsesClass(\SqlFixture\Plan\FixturePlan::class)]
#[UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[UsesClass(\SqlFixture\Plan\Relation::class)]
#[UsesClass(\SqlFixture\Plan\RelationKind::class)]
#[UsesClass(\SqlFixture\Plan\RelationSide::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\RelationCursor::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
final class PlanStructureExceptionTest extends TestCase
{
    #[Test]
    public function testColumnsBoundTwiceNamesTheColumnAndBothParents(): void
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
    public function testCycleShowsTheLoopClosingBackOnItself(): void
    {
        $message = PlanStructureException::cycle(['a', 'b', 'c'])->getMessage();

        self::assertSame(
            'The relations form a cycle: a -> b -> c -> a. Each table would have to be '
            . 'generated before itself, so there is no order that satisfies them.',
            $message
        );
    }

    #[Test]
    public function testUnboundedSelfReferenceSuggestsTheOptionalMarker(): void
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

}
