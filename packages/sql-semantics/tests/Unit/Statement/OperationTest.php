<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Shape\AbsentField;
use SqlSemantics\Statement\Shape\AmbiguousFields;
use SqlSemantics\Statement\Shape\DependentField;
use SqlSemantics\Statement\Shape\UniqueField;

#[CoversClass(Operation::class)]
#[Medium]
final class OperationTest extends TestCase
{
    public function testToStringRendersFromTheStructureAndNotFromTheInput(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('select   a  ,b from   t where a>1 /* c */');

        self::assertSame('SELECT a, b FROM t WHERE a > 1', $operation->toString());
    }

    public function testToStringOfANewRootBuiltFromExplicitStructure(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $context = $semantics->context([]);

        $operation = new Operation($context, new Select([new ResultColumn(new IntegerLiteral('7'))]));

        self::assertSame('SELECT 7', $operation->toString());
        self::assertSame($context, $operation->context);
        self::assertInstanceOf(IntegerLiteral::class, $operation->field(0)->expression);
        self::assertSame('7', $operation->field(0)->expression->digits);
    }

    public function testToStringIsNotReachedForANodeUsedAtTwoPositions(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $literal = new IntegerLiteral('1');

        $this->expectExceptionMessage('A statement node occurs at one position only.');

        new Operation($semantics->context([]), new Select([new ResultColumn($literal), new ResultColumn($literal)]));
    }

    public function testProfileIsTheProfileOfTheContext(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        $operation = $semantics->analyze('SELECT 1');

        self::assertSame($semantics->profile(), $operation->profile());
        self::assertSame($operation->context->profile, $operation->profile());
    }

    public function testDeclarationsAnswerTheTablesAStatementProvides(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INTEGER, b TEXT)');

        self::assertCount(1, $operation->declarations());
        self::assertSame('t', $operation->declarations()[0]->name->name->value);
        self::assertSame(['a', 'b'], array_map(static fn ($column): string => $column->name->value, $operation->declarations()[0]->columns));
        self::assertSame([], (new Semantics(Dialect::Sqlite))->analyze('SELECT 1')->declarations());
    }

    public function testShapeIsNullWhenTheStatementReturnsNoRows(): void
    {
        self::assertNull((new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INTEGER)')->shape());
    }

    public function testShapeStaysOpenWhileAStarCannotBeExpanded(): void
    {
        $shape = (new Semantics(Dialect::Sqlite))->analyze('SELECT *, 1 FROM t')->shape();

        self::assertNotNull($shape);
        self::assertFalse($shape->complete());
        self::assertCount(1, $shape->slots);
    }

    public function testFieldsAreNullForAnOpenShapeAndForNoRows(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertNull($semantics->analyze('SELECT * FROM t')->fields());
        self::assertNull($semantics->analyze('DELETE FROM t')->fields());
        self::assertCount(2, $semantics->analyze('SELECT 1, 2')->fields() ?? []);
    }

    public function testFieldFindsByPositionAndByUniqueName(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS a, 2 AS b');

        self::assertSame(1, $operation->field('b')->position);
        self::assertSame('a', $operation->field(0)->name?->value);
    }

    public function testFieldRefusesANameSeveralFieldsShare(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS a, 2 AS a');

        $this->expectExceptionMessage('The name does not denote exactly one output field.');

        $operation->field('a');
    }

    public function testFieldRefusesAPositionOfAnOpenShape(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM t');

        $this->expectExceptionMessage('The statement has no complete field list.');

        $operation->field(0);
    }

    public function testLookupFieldTellsTheOutcomesApart(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze('SELECT 1 AS a, 2 AS a, 3 AS b');

        self::assertInstanceOf(UniqueField::class, $operation->lookupField('b'));
        self::assertInstanceOf(AmbiguousFields::class, $operation->lookupField('a'));
        self::assertInstanceOf(AbsentField::class, $operation->lookupField('c'));
        self::assertInstanceOf(DependentField::class, $semantics->analyze('SELECT * FROM t')->lookupField('a'));
    }

    public function testLookupFieldRefusesAStatementWithoutRows(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('DELETE FROM t');

        $this->expectExceptionMessage('The statement returns no rows.');

        $operation->lookupField('a');
    }

    public function testInputRelationIsTheFromStructureOfASelection(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertInstanceOf(JoinChain::class, $semantics->analyze('SELECT a FROM t JOIN u ON t.a = u.a')->inputRelation());
        self::assertNull($semantics->analyze('SELECT 1')->inputRelation());
        self::assertNull($semantics->analyze('DELETE FROM t')->inputRelation());
    }

    public function testSingleNamedInputAnswersTheOnlyNamedRelation(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT a FROM t AS x');

        self::assertSame('t', $operation->singleNamedInput()->name()->name->value);
        self::assertSame('x', $operation->singleNamedInput()->alias()?->value);
    }

    public function testSingleNamedInputRefusesAJoin(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT a FROM t, u');

        $this->expectExceptionMessage('The statement does not read from exactly one named relation.');

        $operation->singleNamedInput();
    }

    public function testSingleNamedInputRefusesADerivedRelation(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT x FROM (SELECT 1 AS x)');

        $this->expectExceptionMessage('The statement does not read from exactly one named relation.');

        $operation->singleNamedInput();
    }
}
