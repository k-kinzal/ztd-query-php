<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Semantics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Statement\Semantics\ResultFields;
use SqlSemantics\Statement\Shape\AbsentField;
use SqlSemantics\Statement\Shape\AmbiguousFields;
use SqlSemantics\Statement\Shape\DependentField;
use SqlSemantics\Statement\Shape\UniqueField;

#[CoversClass(ResultFields::class)]
#[Medium]
final class ResultFieldsTest extends TestCase
{
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
