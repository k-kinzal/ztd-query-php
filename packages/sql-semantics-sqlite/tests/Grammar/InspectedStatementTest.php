<?php

declare(strict_types=1);

namespace Tests\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\AbsentField;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\AmbiguousFields;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\DependentField;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\UniqueField;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

/**
 * Checks the statement facts and rendering used by the grammar audit.
 */
#[CoversClass(InspectedStatement::class)]
#[Medium]
final class InspectedStatementTest extends TestCase
{
    /**
     * Rendering uses the checked nodes and their current operands.
     */
    public function testToStringRendersFromTheStructureAndNotFromTheInput(): void
    {
        $operation = (new Inspection())->inspect('select   a  ,b from   t where a>1 /* c */');

        self::assertSame('SELECT a, b FROM t WHERE a > 1', $operation->toString());
    }

    /**
     * A directly built grammar fixture can be rendered.
     */
    public function testToStringOfANewRootBuiltFromExplicitStructure(): void
    {
        $semantics = new Inspection();
        $context = $semantics->context([]);

        $operation = new InspectedStatement($context, new Select([new ResultColumn(new IntegerLiteral('7'))]));

        self::assertSame('SELECT 7', $operation->toString());
        self::assertSame($context, $operation->context);
        self::assertInstanceOf(IntegerLiteral::class, $operation->field(0)->expression);
        self::assertSame('7', $operation->field(0)->expression->digits);
    }

    /**
     * The grammar fixture rejects one occurrence reused in distinct positions.
     */
    public function testToStringIsNotReachedForANodeUsedAtTwoPositions(): void
    {
        $semantics = new Inspection();
        $literal = new IntegerLiteral('1');

        $this->expectExceptionMessage('A statement node occurs at one position only.');

        new InspectedStatement($semantics->context([]), new Select([new ResultColumn($literal), new ResultColumn($literal)]));
    }

    /**
     * Fixture analysis uses the explicitly supplied profile.
     */
    public function testProfileIsTheProfileOfTheContext(): void
    {
        $semantics = new Inspection();

        $operation = $semantics->inspect('SELECT 1');

        self::assertSame($semantics->profile(), $operation->profile());
        self::assertSame($operation->context->profile, $operation->profile());
    }

    /**
     * The facts record a table contributed by its declaration.
     */
    public function testDeclarationsAnswerTheTablesAStatementProvides(): void
    {
        $operation = (new Inspection())->inspect('CREATE TABLE t (a INTEGER, b TEXT)');

        self::assertCount(1, $operation->declarations());
        self::assertSame('t', $operation->declarations()[0]->name->name->value);
        self::assertSame(['a', 'b'], array_map(static fn ($column): string => $column->name->value, $operation->declarations()[0]->columns));
        self::assertSame([], (new Inspection())->inspect('SELECT 1')->declarations());
    }

    /**
     * A non-row operation has no output shape.
     */
    public function testShapeIsNullWhenTheStatementReturnsNoRows(): void
    {
        self::assertNull((new Inspection())->inspect('CREATE TABLE t (a INTEGER)')->shape());
    }

    /**
     * An unresolved star marks the fixture output shape as open.
     */
    public function testShapeStaysOpenWhileAStarCannotBeExpanded(): void
    {
        $shape = (new Inspection())->inspect('SELECT *, 1 FROM t')->shape();

        self::assertNotNull($shape);
        self::assertFalse($shape->complete());
        self::assertCount(1, $shape->slots);
    }

    /**
     * Field lists require a complete row shape.
     */
    public function testFieldsAreNullForAnOpenShapeAndForNoRows(): void
    {
        $semantics = new Inspection();

        self::assertNull($semantics->inspect('SELECT * FROM t')->fields());
        self::assertNull($semantics->inspect('DELETE FROM t')->fields());
        self::assertCount(2, $semantics->inspect('SELECT 1, 2')->fields() ?? []);
    }

    /**
     * A complete shape supports ordinal and unique-name lookup.
     */
    public function testFieldFindsByPositionAndByUniqueName(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 AS a, 2 AS b');

        self::assertSame(1, $operation->field('b')->position);
        self::assertSame('a', $operation->field(0)->name?->value);
    }

    /**
     * Name lookup reports a repeated output name.
     */
    public function testFieldRefusesANameSeveralFieldsShare(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 AS a, 2 AS a');

        $this->expectExceptionMessage('The name does not denote exactly one output field.');

        $operation->field('a');
    }

    /**
     * Ordinal lookup requires the complete list of fields.
     */
    public function testFieldRefusesAPositionOfAnOpenShape(): void
    {
        $operation = (new Inspection())->inspect('SELECT * FROM t');

        $this->expectExceptionMessage('The statement has no complete field list.');

        $operation->field(0);
    }

    /**
     * Lookups distinguish unique, absent, ambiguous and dependent results.
     */
    public function testLookupFieldTellsTheOutcomesApart(): void
    {
        $semantics = new Inspection();
        $operation = $semantics->inspect('SELECT 1 AS a, 2 AS a, 3 AS b');

        self::assertInstanceOf(UniqueField::class, $operation->lookupField('b'));
        self::assertInstanceOf(AmbiguousFields::class, $operation->lookupField('a'));
        self::assertInstanceOf(AbsentField::class, $operation->lookupField('c'));
        self::assertInstanceOf(DependentField::class, $semantics->inspect('SELECT * FROM t')->lookupField('a'));
    }

    /**
     * Field lookup is invalid for a non-row operation.
     */
    public function testLookupFieldRefusesAStatementWithoutRows(): void
    {
        $operation = (new Inspection())->inspect('DELETE FROM t');

        $this->expectExceptionMessage('The statement returns no rows.');

        $operation->lookupField('a');
    }

    /**
     * The selection records its FROM structure.
     */
    public function testInputRelationIsTheFromStructureOfASelection(): void
    {
        $semantics = new Inspection();

        self::assertInstanceOf(JoinChain::class, $semantics->inspect('SELECT a FROM t JOIN u ON t.a = u.a')->inputRelation());
        self::assertNull($semantics->inspect('SELECT 1')->inputRelation());
        self::assertNull($semantics->inspect('DELETE FROM t')->inputRelation());
    }

    /**
     * A simple named source can be located from the fixture.
     */
    public function testSingleNamedInputAnswersTheOnlyNamedRelation(): void
    {
        $operation = (new Inspection())->inspect('SELECT a FROM t AS x');

        self::assertSame('t', $operation->singleNamedInput()->name()->name->value);
        self::assertSame('x', $operation->singleNamedInput()->alias()?->value);
    }

    /**
     * A join has no single named input.
     */
    public function testSingleNamedInputRefusesAJoin(): void
    {
        $operation = (new Inspection())->inspect('SELECT a FROM t, u');

        $this->expectExceptionMessage('The statement does not read from exactly one named relation.');

        $operation->singleNamedInput();
    }

    /**
     * A derived relation has no single named input.
     */
    public function testSingleNamedInputRefusesADerivedRelation(): void
    {
        $operation = (new Inspection())->inspect('SELECT x FROM (SELECT 1 AS x)');

        $this->expectExceptionMessage('The statement does not read from exactly one named relation.');

        $operation->singleNamedInput();
    }
}
