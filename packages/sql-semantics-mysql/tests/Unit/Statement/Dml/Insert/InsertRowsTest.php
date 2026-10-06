<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\RowAlias;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(InsertRows::class)]
#[Medium]
final class InsertRowsTest extends TestCase
{
    public function testDeriveStatementChecksTheRows(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('INSERT INTO t (a, b, a) VALUES (1, 2, 3), (4)', [$t]);

        self::assertSame(["Column 'a' specified twice", "Column count doesn't match value count at row 2"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testDeriveStatementAcceptsAnEmptyRowWithoutColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('INSERT INTO t VALUES (), ()', [$t]);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }

    public function testRenderWritesValuesAndUpdates(): void
    {
        self::assertSame('INSERT INTO t VALUES (1), (2) ON DUPLICATE KEY UPDATE a = VALUES(a)', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('insert into t value (1), (2) on duplicate key update a = values(a)')->toString());
    }

    public function testRenderRejectsReplaceWithUpdates(): void
    {
        $this->expectExceptionMessage('REPLACE has neither a row alias nor ON DUPLICATE KEY UPDATE.');

        new InsertRows(new InsertInto(true, null, false, new WriteTarget(new QualifiedName(new Name('t')))), [new InsertedRow([])], new RowAlias(new Name('n')));
    }
}
