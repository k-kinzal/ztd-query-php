<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(InsertQuery::class)]
#[Medium]
final class InsertQueryTest extends TestCase
{
    public function testDeriveStatementComparesTheQueryWidth(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('INSERT INTO t (a) SELECT 1, 2', [$t]);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(ValueCountMismatch::class, $operation->facts->diagnostics[0]);
        self::assertNull($operation->facts->diagnostics[0]->row);
    }

    public function testDeriveStatementLetsUpdatesSeeTheSourceTables(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [new Column(new Name('x'), new Integral(IntegralKind::Int)), new Column(new Name('y'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('INSERT INTO t (a) SELECT x FROM u ON DUPLICATE KEY UPDATE b = u.y + x', [$t, $u]);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheSource(): void
    {
        self::assertSame('INSERT INTO t (SELECT 1 LIMIT 1) UNION SELECT 2', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('insert into t (select 1 limit 1) union select 2')->toString());
        self::assertSame('INSERT INTO t TABLE u', (new Semantics(Dialect::MySql))->analyze('insert t table u')->toString());
    }
}
