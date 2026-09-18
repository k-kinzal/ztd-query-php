<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\Choice\ChoiceValueException;
use SqlFixture\Fixture\GenerationRun;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\Plan\Choice\ChoiceCase;
use SqlFixture\Plan\Choice\ChoiceDefinitionException;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\Parsing\RelationCursor;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationChoice as Subject;

#[CoversClass(Subject::class)]
#[UsesClass(\SqlFixture\Fixture\Choice\CaseSelection::class)]
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
#[UsesClass(\SqlFixture\Plan\RelationKind::class)]
#[UsesClass(\SqlFixture\Plan\RelationSide::class)]
#[UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
final class RelationChoiceTest extends TestCase
{
    public function testOnAcceptsAReferenceOrString(): void
    {
        $ref = ColumnRef::of('comments', 'kind');
        self::assertSame($ref, Subject::on($ref)->discriminator);
        self::assertEquals($ref, Subject::on('comments.kind')->discriminator);
    }

    public function testWhenPreservesTypesAndDoesNotChangeTheOriginal(): void
    {
        $base = Subject::on('comments.kind');
        $relation = Relation::manyToOne('comments.target_id', 'posts.id');
        $choice = $base->when('1', $relation)->when(1)->when(true)->when(null);
        self::assertSame([], $base->cases);
        self::assertSame(['1', 1, true, null], array_column($choice->cases, 'value'));
        self::assertSame([$relation], $choice->cases[0]->relations);
    }

    public function testOtherwiseKeepsExplicitCasesAndTheirOrder(): void
    {
        $base = Subject::on('comments.kind')->when('none');
        $relation = Relation::manyToOne('comments.target_id', 'posts.id');
        $choice = $base->otherwise($relation);
        self::assertNull($base->fallback);
        self::assertSame($base->cases, $choice->cases);
        self::assertSame([$relation], $choice->fallback?->relations);
    }

    public function testBranchesIncludesTheFallbackLast(): void
    {
        $choice = Subject::on('comments.kind')->when('post');
        self::assertSame($choice->cases, $choice->branches());
        $complete = $choice->otherwise();
        self::assertSame([$complete->cases[0], $complete->fallback], $complete->branches());
    }

    public function testRelationsFlattensCasesIncludingTheFallback(): void
    {
        $post = Relation::manyToOne('comments.target_id', 'posts.id');
        $video = Relation::manyToOne('comments.target_id', 'videos.id');
        self::assertSame([$post, $video], Subject::on('comments.kind')->when('post', $post)->otherwise($video)->relations());
    }


    public function testWhenAndOtherwiseNormalizeNamedVariadicRelations(): void
    {
        $relation = Relation::manyToOne('c.id', 'a.id');
        $choice = Subject::on('c.kind')->when('a', ...['parent' => $relation])->when('b')->otherwise(...['parent' => $relation]);
        self::assertSame([$relation], $choice->cases[0]->relations);
        self::assertSame([$relation], $choice->fallback?->relations);
        self::assertSame([$choice->cases[0], $choice->cases[1], $choice->fallback], $choice->branches());
    }

    public function testRelationsRetainsMultipleRequirementsWithinOneBranch(): void
    {
        $a = Relation::manyToOne('c.a_id', 'a.id');
        $b = Relation::manyToOne('c.b_id', 'b.id');
        self::assertSame([$a, $b], Subject::on('c.kind')->when('both', $a, $b)->relations());
    }
}
