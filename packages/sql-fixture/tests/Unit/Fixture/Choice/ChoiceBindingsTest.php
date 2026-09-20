<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\Choice\ChoiceBindings as Subject;
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
#[UsesClass(\SqlFixture\Fixture\Choice\CaseSelection::class)]
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
final class ChoiceBindingsTest extends TestCase
{
    public function testFixClearsOnlyInactiveKeys(): void
    {
        $a = Relation::manyToOne('c.a_id', 'a.id');
        $b = Relation::manyToOne('c.b_id', 'b.id');
        $choice = RelationChoice::on('c.kind')->when('a', $a)->when('b', $b);
        self::assertSame(['a_id' => 3, 'b_id' => null], (new Subject())->fix($choice, $choice->cases[0], ['a_id' => 3], new GenerationRun([])));
    }

    public function testFixRejectsExplicitInactiveReferences(): void
    {
        $choice = RelationChoice::on('c.kind')->when('a', Relation::manyToOne('c.a_id', 'a.id'))->when('none');
        $this->expectException(ChoiceValueException::class);
        (new Subject())->fix($choice, $choice->cases[1], ['a_id' => 3], new GenerationRun([]));
    }

    public function testFixNullsAnAbsentOptionalParent(): void
    {
        $choice = RelationChoice::on('c.kind')->when('a', Relation::manyToOne('c.a_id', 'a.id', true));
        self::assertSame(['a_id' => null], (new Subject())->fix($choice, $choice->cases[0], [], new GenerationRun([])));
        self::assertSame(['a_id' => 7], (new Subject())->fix($choice, $choice->cases[0], ['a_id' => 7], new GenerationRun([])));
        self::assertSame([], (new Subject())->fix($choice, $choice->cases[0], [], new GenerationRun(['a' => RowSpec::from('a', 1)])));
    }

    public function testChildColumnsExcludesKeysOnOtherTables(): void
    {
        $choice = RelationChoice::on('c.kind');
        self::assertSame(['tenant', 'id'], (new Subject())->childColumns($choice, [Relation::manyToOne('c.(tenant, id)', 'a.(tenant, id)'), Relation::oneToMany('c.id', 'd.c_id')]));
    }


    public function testFixProcessesOptionalParentsAfterRequiredRelations(): void
    {
        $choice = RelationChoice::on('c.kind')->when('a', Relation::manyToOne('c.a_id', 'a.id'), Relation::manyToOne('c.b_id', 'b.id', true));
        self::assertSame(['b_id' => null], (new Subject())->fix($choice, $choice->cases[0], [], new GenerationRun([])));
    }

    public function testChildColumnsDeduplicatesOverlappingCompositeKeysAsAList(): void
    {
        $relations = [Relation::manyToOne('c.(tenant, a_id)', 'a.(tenant, id)'), Relation::manyToOne('c.(tenant, b_id)', 'b.(tenant, id)')];
        self::assertSame(['tenant', 'a_id', 'b_id'], (new Subject())->childColumns(RelationChoice::on('c.kind'), $relations));
    }
}
