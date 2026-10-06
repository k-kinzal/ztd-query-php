<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(NestedRelation::class)]
#[Medium]
final class NestedRelationTest extends TestCase
{
    public function testDeriveRelationKeepsTheInnerTablesVisible(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT t.a, c FROM (t, u)', [$t, $u]);

        self::assertSame($t->columns[0], $operation->field(0)->column());
        self::assertSame($u->columns[1], $operation->field(1)->column());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheParentheses(): void
    {
        self::assertSame('SELECT 1 FROM ((t)), ((t JOIN u)), ((t, u), v)', (new Semantics(Dialect::MySql))->analyze('select 1 from ((t)), ((t join u)), ((t, u), v)')->toString());
        self::assertSame('SELECT 1 FROM (t, u)', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 from (t, u)')->toString());
    }
}
