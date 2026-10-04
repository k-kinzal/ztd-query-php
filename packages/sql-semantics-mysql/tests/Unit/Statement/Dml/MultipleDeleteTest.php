<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\UnknownDeleteTable;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(MultipleDelete::class)]
#[Medium]
final class MultipleDeleteTest extends TestCase
{
    public function testDeriveStatementReportsATableOutsideTheReferences(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('DELETE t, z FROM t WHERE a = 1', [$t]);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(UnknownDeleteTable::class, $operation->facts->diagnostics[0]);
        self::assertSame('z', $operation->facts->diagnostics[0]->table->name->value);
    }

    public function testDeriveStatementMatchesCorrelationNames(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [new Column(new Name('x'), new Integral(IntegralKind::Int)), new Column(new Name('y'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('DELETE FROM x USING t AS x JOIN u ON x.a = u.x', [$t, $u]);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesBothSpellings(): void
    {
        self::assertSame('DELETE QUICK t, db.u FROM t JOIN db.u ON t.a = u.a', (new Semantics(Dialect::MySql))->analyze('delete quick t.*, db.u.* from t join db.u on t.a = u.a')->toString());
        self::assertSame('DELETE FROM t USING t, u WHERE t.a = u.a', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('delete from t using t, u where t.a = u.a')->toString());
    }

    public function testRenderRejectsAnEmptyTargetList(): void
    {
        $this->expectExceptionMessage('A multiple-table DELETE names at least one table to delete from.');

        new MultipleDelete(null, [], [], MultipleDeleteForm::Using, [new WriteTarget(new QualifiedName(new Name('t')))]);
    }
}
