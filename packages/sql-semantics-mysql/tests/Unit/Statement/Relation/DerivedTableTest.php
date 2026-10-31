<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Name\MissingTableQualifier;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(DerivedTable::class)]
#[Medium]
final class DerivedTableTest extends TestCase
{
    public function testDeriveRelationNamesTheColumnsAfterTheQueryOrTheList(): void
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
        $plain = $semantics->analyze('SELECT d.a FROM (SELECT a FROM t) AS d', [$t]);
        $listed = $semantics->analyze('SELECT x FROM (SELECT a FROM t) AS d (x)', [$t]);

        self::assertSame($t->columns[0], $plain->field('a')->column());
        self::assertSame($t->columns[0], $listed->field('x')->column());
        self::assertSame([], $listed->diagnostics());
    }

    public function testDeriveRelationSeesTheTablesToTheLeftWhenLateral(): void
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
        $lateral = $semantics->analyze('SELECT x FROM t, LATERAL (SELECT t.a AS x) AS d', [$t]);
        $plain = $semantics->analyze('SELECT x FROM t, (SELECT t.a AS x) AS d', [$t]);

        self::assertSame([], $lateral->diagnostics());
        self::assertSame($t->columns[0], $lateral->field('x')->column());
        self::assertInstanceOf(MissingTableQualifier::class, $plain->diagnostics()[0]);
    }

    public function testDeriveRelationAcceptsAnEmptyQuotedAlias(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 FROM (SELECT 1) AS ``');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $operation->query);
        self::assertInstanceOf(DerivedTable::class, $operation->query->from);
        self::assertSame('', $operation->query->from->alias->value);
        self::assertSame([], $operation->diagnostics());
    }

    public function testRenderWritesTheDerivedTable(): void
    {
        self::assertSame('SELECT 1 FROM LATERAL (SELECT 1) d (x), ((SELECT 2)) e', (new Semantics(Dialect::MySql))->analyze('select 1 from lateral (select 1) d (x), ((select 2)) e')->toString());
        self::assertSame('SELECT 1 FROM ((SELECT 2)) e', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 from ((select 2)) e')->toString());
        self::assertSame('SELECT 1 FROM (SELECT 2 UNION SELECT 3 ORDER BY 1) AS e', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from (select 2 union select 3 order by 1) as e')->toString());
    }
}
