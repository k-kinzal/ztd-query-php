<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Update::class)]
#[Medium]
final class UpdateTest extends TestCase
{
    public function testValueRejectsAnOffsetInTheRowLimit(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('UPDATE t SET a=1');
        self::assertInstanceOf(M\Statement::class, $operation);
        $statement = $operation;
        self::assertInstanceOf(Update::class, $statement);
        $limit = new M\Query\Clause\RowLimit(new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('1'), M\Query\Clause\OffsetSpelling::Comma);
        $this->expectExceptionMessage('UPDATE limits rows without an offset.');
        new Update($statement->with, $statement->lowPriority, $statement->ignore, $statement->tables, $statement->assignments, limit: $limit);
    }

    public function testValueRejectsAnOutputPositionInWriteOrdering(): void
    {
        $this->expectExceptionMessage('UPDATE ordering uses expressions');

        new Update(null, false, false, [new TableReference(new QualifiedName(new Name('t')))], [new M\Dml\Assignment(new M\Name\ColumnUse(new Name('a')), new M\Literal\NumberLiteral('1'))], orderBy: [new M\Query\OrderItem(new M\Query\Clause\OutputOrdinal(new M\Literal\NumberLiteral('1')))]);
    }

    public function testDeriveStatementResolvesAssignmentsAgainstEveryTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [new Column(new Name('x'), new Integral(IntegralKind::Int)), new Column(new Name('y'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('UPDATE t JOIN u ON t.a = u.x SET b = y WHERE a > 0', [$t, $u]);
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertInstanceOf(Update::class, $operation);
        $column = $operation->assignments[0]->column->scalarInformation();
        $value = $operation->assignments[0]->value->scalarInformation();

        self::assertInstanceOf(ResolvedColumn::class, $column->resolution);
        self::assertSame('b', $column->resolution->slot->name?->value);
        self::assertInstanceOf(ResolvedColumn::class, $value->resolution);
        self::assertSame([], $operation->diagnostics());
        self::assertNull($operation->output());
    }

    public function testHasExecutionConditionRetainsMultiTableClauses(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('UPDATE t, u SET a = 1 ORDER BY a LIMIT 1');
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertInstanceOf(Update::class, $operation);
        self::assertTrue($operation->hasExecutionCondition());
        self::assertCount(2, $operation->tables);
        self::assertCount(1, $operation->orderBy);
        self::assertNotNull($operation->limit);
    }

    public function testHasExecutionConditionRetainsADerivedDestination(): void
    {
        $source = new M\Relation\DerivedTable(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name('a'))]), new Name('q'));
        $statement = new Update(null, false, false, [$source], [new M\Dml\Assignment(new M\Name\ColumnUse(new Name('a')), new M\Literal\NumberLiteral('2'))]);
        self::assertTrue($statement->hasExecutionCondition());
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('WITH c AS (SELECT 1 AS x) UPDATE LOW_PRIORITY IGNORE t SET a = a + 1 WHERE b IN (SELECT x FROM c) ORDER BY a DESC LIMIT 3', (new Semantics(Dialect::MySql))->analyze('with c as (select 1 as x) update low_priority ignore t set a = a + 1 where b in (select x from c) order by a desc limit 3')->toString());
        self::assertSame('UPDATE t SET a = 1 LIMIT 2', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('update t set a = 1 limit 2')->toString());
    }

    public function testRenderRejectsAnUpdateWithoutAssignment(): void
    {
        $this->expectExceptionMessage('UPDATE holds at least one assignment.');

        new Update(null, false, false, [new TableReference(new QualifiedName(new Name('t')))], []);
    }

    public function testRenderWritesTheHintCommentAfterTheVerb(): void
    {
        self::assertSame('WITH c AS (SELECT 1) UPDATE /*+ NO_BKA(`t`) */ t SET a = 1', (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT 1) UPDATE /*+ no_bka(t) */ t SET a = 1')->toString());
    }

    public function testTablesFlattenAnUnparenthesizedCommaList(): void
    {
        $first = new TableReference(new QualifiedName(new Name('a')));
        $second = new TableReference(new QualifiedName(new Name('b')));
        $third = new TableReference(new QualifiedName(new Name('c')));
        $update = new Update(null, false, false, [$first, new M\Relation\TableList([$second, $third])], [new M\Dml\Assignment(new M\Name\ColumnUse(new Name('id')), new M\Literal\NumberLiteral('1'))]);

        self::assertSame([$first, $second, $third], $update->tables);
    }

    public function testHasExecutionConditionRetainsAnIntegerOrderingPosition(): void
    {
        $statement = new Update(null, false, false, [new TableReference(new QualifiedName(new Name('t')))], [new M\Dml\Assignment(new M\Name\ColumnUse(new Name('a')), new M\Literal\NumberLiteral('1'))], orderBy: [new M\Query\OrderItem(new M\Literal\NumberLiteral('1'))]);
        self::assertTrue($statement->hasExecutionCondition());
    }

    public function testDeriveStatementStoredRetainsRowAssignmentsBeforeOperationConstruction(): void
    {
        $table = new TableReference(new QualifiedName(new Name('t')));
        $column = new M\Name\ColumnUse(new Name('a'));
        $value = new M\Expression\Row([new M\Name\ColumnUse(new Name('b')), new M\Literal\NumberLiteral('2')]);
        $assignment = new M\Routine\Program\Write\DeferredColumnAssignment($column, $value);
        $write = new Update(null, false, false, [$table], [$assignment]);
        self::assertSame($assignment, $write->assignments[0]);
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context([], true));
        $write->deriveStatement($derivation);
        self::assertTrue($table->hasBinding());
        self::assertTrue($column->hasBinding());
        self::assertTrue($value->hasBinding());
        self::assertContains(M\Expression\Problem\OperandColumns::class, array_map(static fn ($problem): string => $problem::class, $derivation->facts()->diagnostics));
    }


    public function testValueRejectsAnOffsetInTheStoredRowLimit(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() UPDATE t,u SET t.a=1 LIMIT 1');
        self::assertInstanceOf(M\Statement::class, $operation);
        $statement = $operation;
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $statement);
        $statement = $statement->body;
        self::assertInstanceOf(Update::class, $statement);
        $limit = new M\Query\Clause\RowLimit(new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('1'), M\Query\Clause\OffsetSpelling::Comma);
        $this->expectExceptionMessage('UPDATE limits rows without an offset.');
        new Update($statement->with, $statement->lowPriority, $statement->ignore, $statement->tables, $statement->assignments, limit: $limit);
    }

    public function testDeriveStatementStoredRetainsTheCompleteWrite(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() UPDATE JSON_TABLE('[]', '$[*]' COLUMNS (b FOR ORDINALITY,b FOR ORDINALITY)) j SET b=1");
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(Update::class, $operation->body);
        self::assertStringContainsString('UPDATE JSON_TABLE', $operation->toString());
    }
    public function testRenderPreservesTheStoredUpdate(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() UPDATE LOW_PRIORITY IGNORE JSON_TABLE('[]', '$[*]' COLUMNS (b FOR ORDINALITY,b FOR ORDINALITY)) j SET b=1 WHERE 0 ORDER BY b LIMIT 2");
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertStringContainsString('UPDATE LOW_PRIORITY IGNORE', $operation->toString());
        self::assertStringContainsString('WHERE 0 ORDER BY b LIMIT 2', $operation->toString());
    }
    public function testValuePreservesTheOriginalOrdinaryOperands(): void
    {
        $table = new TableReference(new QualifiedName(new Name('t')));
        $assignment = new M\Dml\Assignment(new M\Name\ColumnUse(new Name('a')), new M\Literal\NumberLiteral('1'));
        $order = new M\Query\OrderItem(new M\Name\ColumnUse(new Name('a')));
        $write = new Update(null, false, false, [$table], [$assignment], null, [$order], null, []);
        self::assertSame([$table], $write->tables);
        self::assertSame([$assignment], $write->assignments);
        self::assertSame([$order], $write->orderBy);
    }
}
