<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
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
        $statement = (new Semantics(Dialect::MySql))->analyze('UPDATE t SET a=1')->statement;
        self::assertInstanceOf(Update::class, $statement);
        $limit = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), \SqlSemantics\Platform\MySql\Statement\Query\Clause\OffsetSpelling::Comma);
        $this->expectExceptionMessage('UPDATE limits rows without an offset.');
        new Update($statement->with, $statement->lowPriority, $statement->ignore, $statement->tables, $statement->assignments, limit: $limit);
    }

    public function testValueRejectsAnOutputPositionInWriteOrdering(): void
    {
        $this->expectExceptionMessage('UPDATE ordering uses expressions');

        new Update(null, false, false, [new TableReference(new QualifiedName(new Name('t')))], [new \SqlSemantics\Platform\MySql\Statement\Dml\Assignment(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new Name('a')), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))], orderBy: [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')))]);
    }

    public function testDeriveStatementResolvesAssignmentsAgainstEveryTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [new Column(new Name('x'), new Integral(IntegralKind::Int)), new Column(new Name('y'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('UPDATE t JOIN u ON t.a = u.x SET b = y WHERE a > 0', [$t, $u]);
        self::assertInstanceOf(Update::class, $operation->statement);
        $column = $operation->facts->scalar($operation->statement->assignments[0]->column);
        $value = $operation->facts->scalar($operation->statement->assignments[0]->value);

        self::assertInstanceOf(ResolvedColumn::class, $column->resolution);
        self::assertSame('b', $column->resolution->slot->name?->value);
        self::assertInstanceOf(ResolvedColumn::class, $value->resolution);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }

    public function testDeriveStatementRetainsRefusedMultiTableClausesOutsideTheOrdinaryRole(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('UPDATE t, u SET a = 1 ORDER BY a LIMIT 1');
            self::fail('An ordinary multi-table UPDATE cannot order or limit its rows.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\RefusedUpdate::class, $failure->problem);
            self::assertCount(2, $failure->problem->write->tables);
            self::assertCount(1, $failure->problem->write->orderBy);
            self::assertNotNull($failure->problem->write->limit);
        }
    }

    public function testValueRejectsAnUpdateWithOnlyADerivedDestination(): void
    {
        $source = new \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new Name('a'))]), new Name('q'));
        $this->expectExceptionMessage('An ordinary UPDATE has writable targets and compatible clauses.');
        new Update(null, false, false, [$source], [new \SqlSemantics\Platform\MySql\Statement\Dml\Assignment(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new Name('a')), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'))]);
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
        $update = new Update(null, false, false, [$first, new \SqlSemantics\Platform\MySql\Statement\Relation\TableList([$second, $third])], [new \SqlSemantics\Platform\MySql\Statement\Dml\Assignment(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new Name('id')), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);

        self::assertSame([$first, $second, $third], $update->tables);
    }

}
