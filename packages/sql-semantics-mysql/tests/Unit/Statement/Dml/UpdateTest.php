<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
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

    public function testDeriveStatementReportsOrderAndLimitOfAMultipleTableUpdate(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [new Column(new Name('x'), new Integral(IntegralKind::Int)), new Column(new Name('y'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('UPDATE t, u SET a = 1 ORDER BY a LIMIT 1', [$t, $u]);

        self::assertSame(['Incorrect usage of UPDATE and ORDER BY', 'Incorrect usage of UPDATE and LIMIT'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('WITH c AS (SELECT 1 AS x) UPDATE LOW_PRIORITY IGNORE t SET a = a + 1 WHERE b IN (SELECT x FROM c) ORDER BY a DESC LIMIT 3', (new Semantics(Dialect::MySql))->analyze('with c as (select 1 as x) update low_priority ignore t set a = a + 1 where b in (select x from c) order by a desc limit 3')->toString());
        self::assertSame('UPDATE t SET a = 1 LIMIT 2', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('update t set a = 1 limit 2')->toString());
    }

    public function testRenderRejectsAnUpdateWithoutAssignment(): void
    {
        $this->expectExceptionMessage('UPDATE holds at least one assignment.');

        new Update(null, false, false, [new WriteTarget(new QualifiedName(new Name('t')))], []);
    }
}
