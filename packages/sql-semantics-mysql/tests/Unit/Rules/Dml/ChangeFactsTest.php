<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Dml\ChangeFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\UnknownDeleteTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ChangeFacts::class)]
#[Medium]
final class ChangeFactsTest extends TestCase
{
    public function testUpdateSeesEveryTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [new Column(new Name('x'), new Integral(IntegralKind::Int)), new Column(new Name('y'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('UPDATE t, u SET t.b = u.y WHERE a = x', [$t, $u]);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeleteSeesTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('DELETE FROM t WHERE z = 1', [$t]);

        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testDeleteMultipleChecksTheTargets(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('DELETE t FROM t AS x', [$t]);

        self::assertInstanceOf(UnknownDeleteTable::class, $operation->facts->diagnostics[0]);
    }

    public function testUpdatableRefusesDerivedAndCommonTables(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('WITH c AS (SELECT 1 AS k) UPDATE t, c, (SELECT 2 AS m) AS d SET c.k = 1, d.m = 2, t.a = 3', [$t]);

        self::assertSame([WriteRule::NonUpdatableTarget, WriteRule::NonUpdatableTarget], array_map(static fn ($diagnostic) => $diagnostic instanceof WriteMisuse ? $diagnostic->rule : null, $operation->facts->diagnostics));
    }

    public function testBaseBindsTheCommonTables(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT 1 AS k) DELETE t FROM t JOIN c ON t.a = c.k');

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testReferencesLetLaterTablesSeeEarlierOnes(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('UPDATE t, LATERAL (SELECT t.a AS k) AS d SET t.b = d.k', [$t]);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testClausesDeriveLimitWithoutTables(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('DELETE FROM t ORDER BY b LIMIT ?', [$t]);
        self::assertInstanceOf(Delete::class, $operation->statement);
        self::assertInstanceOf(RowLimit::class, $operation->statement->limit);

        self::assertInstanceOf(Dependent::class, $operation->facts->scalar($operation->statement->limit->count)->type);
    }
}
