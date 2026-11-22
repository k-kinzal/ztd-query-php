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
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DerivedTable::class)]
#[Medium]
final class DerivedTableTest extends TestCase
{
    public function testDeriveRelationNamesTheColumnsAfterTheQueryOrTheList(): void
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
        $plain = $semantics->diagnose('SELECT d.a FROM (SELECT a FROM t) AS d', [$t]);
        $listed = $semantics->diagnose('SELECT x FROM (SELECT a FROM t) AS d (x)', [$t]);

        self::assertSame($t->columns[0], $plain->field('a')->column());
        self::assertSame($t->columns[0], $listed->field('x')->column());
        self::assertSame([], $listed->facts->diagnostics);
    }

    public function testDeriveRelationSeesTheTablesToTheLeftWhenLateral(): void
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
        $lateral = $semantics->diagnose('SELECT x FROM t, LATERAL (SELECT t.a AS x) AS d', [$t]);
        $plain = $semantics->diagnose('SELECT x FROM t, (SELECT t.a AS x) AS d', [$t]);

        self::assertSame([], $lateral->facts->diagnostics);
        self::assertSame($t->columns[0], $lateral->field('x')->column());
        self::assertInstanceOf(MissingColumn::class, $plain->facts->diagnostics[0]);
    }

    public function testDeriveRelationReportsAMissingAlias(): void
    {
        $operation = (new Inspection())->diagnose('SELECT 1 FROM (SELECT 1)');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $operation->facts->diagnostics[0]);
        self::assertSame(MisuseRule::DerivedWithoutAlias, $operation->facts->diagnostics[0]->rule);
    }

    public function testRenderWritesTheDerivedTable(): void
    {
        self::assertSame('SELECT 1 FROM LATERAL (SELECT 1) d (x), ((SELECT 2)) e', (new Inspection())->diagnose('select 1 from lateral (select 1) d (x), ((select 2)) e')->toString());
        self::assertSame('SELECT 1 FROM ((SELECT 2)) e', (new Inspection('mysql-5.7.44'))->diagnose('select 1 from ((select 2)) e')->toString());
        self::assertSame('SELECT 1 FROM (SELECT 2 UNION SELECT 3 ORDER BY 1) AS e', (new Inspection('mysql-5.6.51'))->diagnose('select 1 from (select 2 union select 3 order by 1) as e')->toString());
    }
}
