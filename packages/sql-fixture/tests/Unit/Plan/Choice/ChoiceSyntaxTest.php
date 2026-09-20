<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\Choice\ChoiceValueException;
use SqlFixture\Fixture\GenerationRun;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\Plan\Choice\ChoiceCase;
use SqlFixture\Plan\Choice\ChoiceDefinitionException;
use SqlFixture\Plan\Choice\ChoiceSyntax as Subject;
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
#[UsesClass(\SqlFixture\Fixture\Choice\RowChoices::class)]
#[UsesClass(GenerationRun::class)]
#[UsesClass(\SqlFixture\Fixture\OverrideRows::class)]
#[UsesClass(RowSpec::class)]
#[UsesClass(\SqlFixture\Fixture\TableOverrides::class)]
#[UsesClass(ChoiceCase::class)]
#[UsesClass(ChoiceDefinitionException::class)]
#[UsesClass(\SqlFixture\Plan\Choice\ChoiceLiteral::class)]
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
final class ChoiceSyntaxTest extends TestCase
{
    public function testParseReadsCasesAndFallback(): void
    {
        $choice = (new Subject())->parse("choice comments.kind { 'post' { comments.target_id > posts.id } null {} otherwise { comments.target_id > videos.id } }");
        self::assertSame('comments.kind', $choice->discriminator->toString());
        self::assertSame(['post', null], array_column($choice->cases, 'value'));
        self::assertCount(1, $choice->fallback->relations ?? []);
    }

    public function testPrintPreservesCompositeRelationsAndEscapedLiterals(): void
    {
        $choice = RelationChoice::on('comments.kind')->when("post's", Relation::manyToOne('comments.(tenant, target_id)', 'posts.(tenant, id)'))->otherwise();
        $text = (new Subject())->print($choice);
        self::assertSame("choice comments.kind { 'post''s' { comments.(tenant, target_id) > posts.(tenant, id) } otherwise {  } }", $text);
        self::assertEquals($choice, (new Subject())->parse($text));
    }

    public function testReadRelationsAllowsEmptyBranchesAndQuotedNames(): void
    {
        self::assertSame([], (new Subject())->readRelations(new RelationCursor('{}')));
        $relations = (new Subject())->readRelations(new RelationCursor('{ `a}`.id < b.a_id }'));
        self::assertSame('a}', $relations[0]->parent()->table);
    }


    public function testParseKeepsOperatorTextInsideValues(): void
    {
        $plan = FixturePlan::from("choice c.kind { '<>' { c.id > a.id } }");
        self::assertSame('<>', $plan->choices[0]->cases[0]->value);
        self::assertSame($plan->toString(), FixturePlan::from($plan->toString())->toString());
    }

    public function testParseAcceptsCompactFallbackAndTrailingWhitespace(): void
    {
        $choice = (new Subject())->parse("choice c.kind { 'a' {} otherwise{c.id > a.id} }  \n");
        self::assertSame('a', $choice->fallback?->relations[0]->parent()->table);
    }
}
