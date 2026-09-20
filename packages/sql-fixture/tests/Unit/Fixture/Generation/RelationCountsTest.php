<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture\Generation;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\Generation\RelationCounts as Subject;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\Plan\Relation;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\OverrideRows::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(RowSpec::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\TableOverrides::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\ColumnRef::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\FixturePlan::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Relation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\RelationKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\RelationSide::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\RelationCursor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\DuplicateColumnBindingException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\CyclicDependencyException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\UnboundedSelfReferenceException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Choice\ChoiceValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Choice\PlanContents::class)]
final class RelationCountsTest extends TestCase
{
    public function testResolveCountHonorsExplicitRowsAndCardinality(): void
    {
        $faker = Factory::create();
        $faker->seed(123);
        $counts = new Subject($faker);
        $relation = Relation::oneToMany('a.id', 'b.a_id');
        self::assertSame(7, $counts->resolveCount(RowSpec::from('b', 7), $relation));
        self::assertSame(1, $counts->resolveCount(RowSpec::unspecified(), Relation::oneToOne('a.id', 'b.a_id')));
        $count = $counts->resolveCount(RowSpec::unspecified(), $relation);
        self::assertGreaterThanOrEqual(1, $count);
        self::assertLessThanOrEqual(5, $count);
    }
}
