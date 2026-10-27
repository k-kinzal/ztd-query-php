<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(OdbcJoin::class)]
#[Medium]
final class OdbcJoinTest extends TestCase
{
    public function testDeriveRelationDerivesTheEscapedReference(): void
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
        $operation = $semantics->analyze('SELECT c FROM { OJ t LEFT JOIN u ON t.a = u.a }', [$t, $u]);

        self::assertSame(Nullability::Nullable, $operation->field('c')->nullability);
        self::assertSame([], $operation->diagnostics());
    }

    public function testRenderWritesTheEscape(): void
    {
        self::assertSame('SELECT 1 FROM { OJ t LEFT JOIN u ON t.a = u.a }', (new Semantics(Dialect::MySql))->analyze('select 1 from {oj t left join u on t.a=u.a}')->toString());
    }

    public function testValueRejectsAnUnparenthesizedCommaList(): void
    {
        $this->expectExceptionMessage('An ODBC escape holds a table factor or join; a comma list or nested escape requires parentheses.');
        new OdbcJoin(new \SqlSemantics\Platform\MySql\Statement\Relation\TableList([
            new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t'))),
            new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('u'))),
        ]));
    }

    public function testValueRejectsAnUnparenthesizedNestedEscape(): void
    {
        $this->expectExceptionMessage('An ODBC escape holds a table factor or join; a comma list or nested escape requires parentheses.');
        new OdbcJoin(new OdbcJoin(new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t')))));
    }

    public function testRenderRetainsAParenthesizedCommaList(): void
    {
        self::assertSame('SELECT 1 FROM { OJ (t, u) }', (new Semantics(Dialect::MySql))->analyze('SELECT 1 FROM { OJ (t, u) }')->toString());
    }

}
