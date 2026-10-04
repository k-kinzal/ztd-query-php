<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\RowAlias;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(RowAlias::class)]
#[Medium]
final class RowAliasTest extends TestCase
{
    public function testDeriveRelationNamesTheWrittenColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('INSERT INTO t (a, b) VALUES (1, 2) AS n (m, k) ON DUPLICATE KEY UPDATE b = n.m + k', [$t]);
        self::assertInstanceOf(InsertRows::class, $operation->statement);
        self::assertNotNull($operation->statement->alias);
        $shape = $operation->facts->relation($operation->statement->alias)->shape;

        self::assertSame(['m', 'k'], array_map(static fn ($slot): ?string => $slot->name?->value, $shape->slots));
        self::assertSame(Nullability::NotNull, $shape->slots[0]->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveRelationReportsAnotherColumnCount(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('INSERT INTO t VALUES (1, 2) AS n (m) ON DUPLICATE KEY UPDATE b = m', [$t]);

        self::assertSame('In definition of view, derived table or common table expression, SELECT list and column names list have different column counts (2 and 1).', $operation->facts->diagnostics[0]->message());
    }

    public function testDeriveRelationDependsOnAnUndeclaredTable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('INSERT INTO t VALUES (1) AS n ON DUPLICATE KEY UPDATE a = n.a');
        self::assertInstanceOf(InsertRows::class, $operation->statement);
        self::assertNotNull($operation->statement->alias);

        self::assertFalse($operation->facts->relation($operation->statement->alias)->shape->complete());
    }

    public function testRenderWritesTheAliasAndColumns(): void
    {
        self::assertSame('INSERT INTO t VALUES (1) AS `new` (m) ON DUPLICATE KEY UPDATE a = m', (new Semantics(Dialect::MySql))->analyze('insert into t values (1) as new (m) on duplicate key update a = m')->toString());
    }
}
