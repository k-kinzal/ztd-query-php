<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture\Choice;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\Choice\ChoiceValueException;
use SqlFixture\Fixture\Choice\RowChoices as Subject;
use SqlFixture\Fixture\GenerationRun;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\Plan\Choice\ChoiceCase;
use SqlFixture\Plan\Choice\ChoiceDefinitionException;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\Parsing\RelationCursor;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationChoice;

#[CoversClass(Subject::class)]
#[UsesClass(\SqlFixture\Fixture\Choice\CaseSelection::class)]
#[UsesClass(\SqlFixture\Fixture\Choice\ChoiceBindings::class)]
#[UsesClass(ChoiceValueException::class)]
#[UsesClass(\SqlFixture\Fixture\Choice\InverseRelations::class)]
#[UsesClass(\SqlFixture\Fixture\Choice\ResolvedRow::class)]
#[UsesClass(GenerationRun::class)]
#[UsesClass(\SqlFixture\Fixture\OverrideRows::class)]
#[UsesClass(RowSpec::class)]
#[UsesClass(\SqlFixture\Fixture\TableOverrides::class)]
#[UsesClass(ChoiceCase::class)]
#[UsesClass(ChoiceDefinitionException::class)]
#[UsesClass(\SqlFixture\Plan\Choice\ChoiceLiteral::class)]
#[UsesClass(\SqlFixture\Plan\Choice\ChoiceSyntax::class)]
#[UsesClass(\SqlFixture\Plan\Choice\ChoiceValidation::class)]
#[UsesClass(\SqlFixture\Plan\Choice\PlanContents::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CyclicDependencyException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\DuplicateColumnBindingException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnboundedSelfReferenceException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
#[UsesClass(FixturePlan::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[UsesClass(RelationCursor::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[UsesClass(Relation::class)]
#[UsesClass(RelationChoice::class)]
#[UsesClass(\SqlFixture\Plan\RelationKind::class)]
#[UsesClass(\SqlFixture\Plan\RelationSide::class)]
#[UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
final class RowChoicesTest extends TestCase
{
    public function testResolveSelectsPerRowWithoutChangingTheSourcePlan(): void
    {
        $a = Relation::manyToOne('c.id', 'a.id');
        $b = Relation::manyToOne('c.id', 'b.id');
        $choice = RelationChoice::on('c.kind')->when('a', $a)->when('b', $b);
        $plan = new FixturePlan($choice);
        $row = (new Subject())->resolve($plan, 'c', ['kind' => 'b'], null, new GenerationRun([]), Factory::create());
        self::assertSame([$b], $row->plan->relations);
        self::assertSame(['kind' => 'b'], $row->values);
        self::assertSame([$choice], $plan->choices);
        self::assertSame([], $row->plan->choices);
    }

    public function testResolveInfersInverseValuesAndKeepsOtherChoices(): void
    {
        $a = Relation::manyToOne('c.id', 'a.id');
        $choice = RelationChoice::on('c.kind')->when('a', $a)->when('none');
        $other = RelationChoice::on('x.kind')->when('none');
        $plan = new FixturePlan($choice, $other);
        $row = (new Subject())->resolve($plan, 'c', ['id' => 7], $a, new GenerationRun([]), Factory::create());
        self::assertSame(['id' => 7, 'kind' => 'a'], $row->values);
        self::assertSame($a, $row->arrivedBy);
        self::assertSame([$other], $row->plan->choices);
    }

    public function testResolveKeepsExplicitNullAndClearsInactiveKeys(): void
    {
        $plan = new FixturePlan(RelationChoice::on('c.kind')->when('a', Relation::manyToOne('c.id', 'a.id'))->when(null));
        $row = (new Subject())->resolve($plan, 'c', ['kind' => null], null, new GenerationRun([]), Factory::create());
        self::assertSame(['kind' => null, 'id' => null], $row->values);
        self::assertSame([], $row->plan->relations);
    }


    public function testResolveSkipsEarlierChoicesForOtherTables(): void
    {
        $other = RelationChoice::on('x.kind')->when('none');
        $choice = RelationChoice::on('c.kind')->when('none');
        $row = (new Subject())->resolve(new FixturePlan($other, $choice), 'c', [], null, new GenerationRun([]), Factory::create());
        self::assertSame(['kind' => 'none'], $row->values);
        self::assertSame([$other], $row->plan->choices);
    }

    public function testResolveReplacesAnEquivalentInverseCandidateWithTheSelectedEdge(): void
    {
        $first = Relation::manyToOne('c.id', 'a.id');
        $second = Relation::manyToOne('c.id', 'a.id');
        $choice = RelationChoice::on('c.kind')->when('first', $first)->when('second', $second);
        $row = (new Subject())->resolve(new FixturePlan($choice), 'c', ['kind' => 'second'], $first, new GenerationRun([]), Factory::create());
        self::assertSame($second, $row->arrivedBy);
        self::assertSame([$second], $row->plan->relations);
    }

    public function testResolvePreservesAnUnconditionalArrival(): void
    {
        $arrival = Relation::oneToMany('users.id', 'c.user_id');
        $plan = new FixturePlan($arrival, RelationChoice::on('c.kind')->when('none'));
        $row = (new Subject())->resolve($plan, 'c', [], $arrival, new GenerationRun([]), Factory::create());
        self::assertSame($arrival, $row->arrivedBy);
    }
}
