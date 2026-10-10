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
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Query;
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

    public function testUpdateReportsItsOneTableThatIsNotUpdatableBeforeTheAssignments(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('UPDATE (SELECT 1 AS a) AS d SET nosuch = 1');

        self::assertSame([WriteRule::NonUpdatableTarget, null], array_map(static fn ($diagnostic) => $diagnostic instanceof WriteMisuse ? $diagnostic->rule : null, $operation->facts->diagnostics));
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

    public function testLabelAnswersTheCorrelationNameOrTheTableName(): void
    {
        $query = (new Semantics(Dialect::MySql))->analyze('SELECT 1')->statement;
        self::assertInstanceOf(Query::class, $query);

        self::assertSame('x', (new ChangeFacts())->label(new TableReference(new QualifiedName(new Name('t')), new Name('x')))?->value);
        self::assertSame('t', (new ChangeFacts())->label(new TableReference(new QualifiedName(new Name('t'))))?->value);
        self::assertSame('d', (new ChangeFacts())->label(new DerivedTable($query, new Name('d')))?->value);
        self::assertNull((new ChangeFacts())->label(new Dual()));
    }

    public function testDeleteMultipleNamesATargetThatIsNotUpdatable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT 1 AS k) DELETE z FROM c AS z');

        self::assertEquals([new WriteMisuse(WriteRule::NonUpdatableTarget, new Name('z'))], $operation->facts->diagnostics);
    }

    public function testDeleteFindsTheInvisibleColumnsOfTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE v (a INT, e INT INVISIBLE)')->declarations();

        self::assertSame([], $semantics->analyze('DELETE FROM v WHERE e = 1 ORDER BY e', $tables)->facts->diagnostics);
    }

    public function testMergedNamesTheFirstTableOfADerivedTableMySql57Merges(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $tables = [...$semantics->analyze('CREATE TABLE t1 (a INT)')->declarations(), ...$semantics->analyze('CREATE TABLE t2 (a INT)')->declarations()];
        $facts = static fn (string $sql): array => $semantics->analyze($sql, $tables)->facts->diagnostics;

        self::assertEquals([new WriteMisuse(WriteRule::NonUpdatableTarget, new Name('x'))], $facts('UPDATE t1, (SELECT * FROM (SELECT * FROM t2 AS x) e) d SET d.a = 1'));
        self::assertEquals([new WriteMisuse(WriteRule::NonUpdatableColumn, new Name('a'))], $facts('UPDATE t1, (SELECT a + 1 AS a FROM t2) d SET d.a = 1'));
        self::assertEquals([new WriteMisuse(WriteRule::NonUpdatableTarget, new Name('d'))], $facts('UPDATE t1, (SELECT a FROM t2 GROUP BY a) d SET d.a = 1'));
    }

    public function testComputedTellsAnItemThatIsNotAColumn(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT a + 1 AS a, b, (c) AS d FROM t')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $statement);

        self::assertSame([true, false, false, false], [(new ChangeFacts())->computed($statement, 'A'), (new ChangeFacts())->computed($statement, 'b'), (new ChangeFacts())->computed($statement, 'd'), (new ChangeFacts())->computed($statement, 'z')]);
    }

    public function testLeadingFindsTheFirstTableThroughJoinsAndMergedDerivedTables(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $joined = $semantics->analyze('SELECT * FROM (t1 AS x JOIN t2) JOIN t3')->statement;
        $derived = $semantics->analyze('SELECT * FROM (SELECT * FROM t2 AS y) AS e, t1')->statement;
        $grouped = $semantics->analyze('SELECT * FROM (SELECT a FROM t2 GROUP BY a) AS e')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $joined);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $derived);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $grouped);

        self::assertSame('x', (new ChangeFacts())->leading($joined->from)?->alias()?->value);
        self::assertSame('y', (new ChangeFacts())->leading($derived->from)?->alias()?->value);
        self::assertNull((new ChangeFacts())->leading($grouped->from));
        self::assertNull((new ChangeFacts())->leading(null));
    }

    public function testBlockAnswersTheQueryBlockThroughParentheses(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT 1')->statement;

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $statement);
        self::assertSame($statement, (new ChangeFacts())->block(new \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery($statement)));
    }
}
