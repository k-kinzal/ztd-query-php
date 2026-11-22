<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(NestedRelation::class)]
#[Medium]
final class NestedRelationTest extends TestCase
{
    public function testDeriveRelationKeepsTheInnerTablesVisible(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->inspect('SELECT t.a, c FROM (t, u)', [$t, $u]);

        self::assertSame($t->columns[0], $operation->field(0)->column());
        self::assertSame($u->columns[1], $operation->field(1)->column());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheParentheses(): void
    {
        self::assertSame('SELECT 1 FROM ((t)), ((t JOIN u)), ((t, u), v)', (new Inspection())->inspect('select 1 from ((t)), ((t join u)), ((t, u), v)')->toString());
        self::assertSame('SELECT 1 FROM (t, u)', (new Inspection('mysql-5.7.44'))->inspect('select 1 from (t, u)')->toString());
    }
}
