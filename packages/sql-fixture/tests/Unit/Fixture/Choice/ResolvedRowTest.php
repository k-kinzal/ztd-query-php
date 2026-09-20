<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\Choice\ChoiceValueException;
use SqlFixture\Fixture\Choice\ResolvedRow as Subject;
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
final class ResolvedRowTest extends TestCase
{
    public function testRetainsTheScopedPlanValuesAndArrival(): void
    {
        $relation = Relation::manyToOne('c.id', 'a.id');
        $plan = new FixturePlan($relation);
        $row = new Subject($plan, ['kind' => 'a'], $relation);
        self::assertSame($plan, $row->plan);
        self::assertSame(['kind' => 'a'], $row->values);
        self::assertSame($relation, $row->arrivedBy);
    }

}
