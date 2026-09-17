<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture\Choice;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\Choice\CaseSelection as Subject;
use SqlFixture\Fixture\Choice\ChoiceValueException;
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
#[UsesClass(\SqlFixture\Fixture\Choice\ChoiceBindings::class)]
#[UsesClass(ChoiceValueException::class)]
#[UsesClass(\SqlFixture\Fixture\Choice\InverseRelations::class)]
#[UsesClass(\SqlFixture\Fixture\Choice\ResolvedRow::class)]
#[UsesClass(\SqlFixture\Fixture\Choice\RowChoices::class)]
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
final class CaseSelectionTest extends TestCase
{
    public function testSelectUsesStrictValues(): void
    {
        $choice = RelationChoice::on('c.kind')->when('1')->when(1)->when(false)->when(null);
        $selector = new Subject();
        self::assertSame($choice->cases[0], $selector->select($choice, ['kind' => '1'], null, Factory::create()));
        self::assertSame($choice->cases[1], $selector->select($choice, ['kind' => 1], null, Factory::create()));
        self::assertSame($choice->cases[2], $selector->select($choice, ['kind' => false], null, Factory::create()));
        self::assertSame($choice->cases[3], $selector->select($choice, ['kind' => null], null, Factory::create()));
    }

    public function testSelectUsesFallbackOnlyForSuppliedValues(): void
    {
        $choice = RelationChoice::on('c.kind')->when('known')->otherwise();
        $selector = new Subject();
        self::assertSame($choice->fallback, $selector->select($choice, ['kind' => 'other'], null, Factory::create()));
        self::assertSame($choice->cases[0], $selector->select($choice, [], null, Factory::create()));
    }

    public function testSelectRejectsUnknownValues(): void
    {
        $this->expectException(ChoiceValueException::class);
        (new Subject())->select(RelationChoice::on('c.kind')->when('known'), ['kind' => 'other'], null, Factory::create());
    }

    public function testSelectInfersTheCaseFromAnInverseRelation(): void
    {
        $a = Relation::manyToOne('c.id', 'a.id');
        $b = Relation::manyToOne('c.id', 'b.id');
        $choice = RelationChoice::on('c.kind')->when('a', $a)->when('b', $b);
        self::assertSame($choice->cases[1], (new Subject())->select($choice, [], $b, Factory::create()));
    }

    public function testSelectRejectsAnInverseFallbackWithoutAValue(): void
    {
        $a = Relation::manyToOne('c.id', 'a.id');
        $choice = RelationChoice::on('c.kind')->when('none')->otherwise($a);
        $this->expectException(ChoiceValueException::class);
        (new Subject())->select($choice, [], $a, Factory::create());
    }

    public function testCheckArrivalRejectsAConflictingExplicitCase(): void
    {
        $a = Relation::manyToOne('c.id', 'a.id');
        $choice = RelationChoice::on('c.kind')->when('a', $a)->when('none');
        $this->expectException(ChoiceValueException::class);
        (new Subject())->checkArrival($choice, $choice->cases[1], $a);
    }

    public function testMatchingArrivalUsesBothEndpointsAndTheOperator(): void
    {
        $a = Relation::manyToOne('c.id', 'a.id');
        $case = new ChoiceCase('a', [$a]);
        self::assertSame($a, (new Subject())->matchingArrival($case, Relation::manyToOne('c.id', 'a.id')));
        self::assertNull((new Subject())->matchingArrival($case, Relation::manyToOne('c.other', 'a.id')));
        self::assertNull((new Subject())->matchingArrival($case, Relation::manyToOne('c.id', 'b.id')));
        self::assertNull((new Subject())->matchingArrival($case, Relation::oneToOne('a.id', 'c.id')));
    }


    public function testSelectDoesNotConstrainAnUnrelatedIncomingRelation(): void
    {
        $choice = RelationChoice::on('c.kind')->when('a', Relation::manyToOne('c.a_id', 'a.id'));
        $arrival = Relation::oneToMany('users.id', 'c.user_id');
        self::assertSame($choice->cases[0], (new Subject())->select($choice, [], $arrival, Factory::create()));
    }
}
