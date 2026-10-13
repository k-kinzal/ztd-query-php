<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\From\FromScope;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(FromScope::class)]
#[Medium]
final class FromScopeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLegacyReleases(): iterable
    {
        yield '5.6' => ['mysql-5.6.51'];
        yield '5.7' => ['mysql-5.7.44'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerLegacyReleases')]
    public function testDerivedHidesOuterQueryColumnsInLegacyReleases(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $operation = $semantics->analyze('SELECT (SELECT x FROM (SELECT t.a AS x) d) FROM t', [$table]);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Name\MissingTableQualifier::class, $operation->facts->diagnostics[0]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerLegacyReleases')]
    public function testDerivedKeepsRoutineVariablesAndTriggerRows(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $procedure = $semantics->analyze('CREATE PROCEDURE p(IN x INT) BEGIN DECLARE y INT DEFAULT 3; SELECT * FROM (SELECT x,y) d; END');
        $trigger = $semantics->analyze('CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET NEW.a=(SELECT v FROM (SELECT NEW.a+OLD.a AS v) d)', [$table]);

        self::assertSame([], $procedure->facts->diagnostics);
        self::assertSame([], $trigger->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $semantics->analyze('CREATE TRIGGER bad BEFORE DELETE ON t FOR EACH ROW SET @v=(SELECT x FROM (SELECT NEW.a AS x) d)', [$table])->facts->diagnostics[0]);
    }

    public function testDerivedRetainsModernOuterQueryScopes(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $environment = new \SqlSemantics\Resolution\Environment($semantics->context());

        self::assertSame($environment, (new FromScope())->derived($environment));
        self::assertSame([], $semantics->analyze('SELECT (SELECT x FROM (SELECT t.a AS x) d) FROM t', [$table])->facts->diagnostics);
    }

    public function testOpenMakesATableVisibleUnderItsCorrelationName(): void
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
        $operation = $semantics->analyze('SELECT x.a FROM t AS x', [$t]);
        $wrong = $semantics->analyze('SELECT t.a FROM t AS x', [$t]);

        self::assertSame($t->columns[0], $operation->field('a')->column());
        self::assertInstanceOf(MissingColumn::class, $wrong->facts->diagnostics[0]);
    }

    public function testUniqueReportsTheFirstNameTwoRelationsOfOneDatabaseTake(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $messages = static fn (string $sql): array => array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze($sql, $semantics->context(null, false, new SearchPath('fz')))->facts->diagnostics);

        self::assertSame(["Not unique table/alias: 't2'."], $messages('SELECT 1 FROM t2 JOIN t3 AS t2'));
        self::assertSame(["Not unique table/alias: 't'."], $messages('SELECT 1 FROM fz.t, t'));
        self::assertSame(["Not unique table/alias: 'a'."], $messages('SELECT 1 FROM (SELECT 1) a, (SELECT 2) a'));
        self::assertSame([], $messages('SELECT 1 FROM d.t, e.t'));
        self::assertSame([], $messages('SELECT 1 FROM mysql.user, fz.t1 user'));
    }

    public function testEnterReportsARowAsTheJoinCondition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 FROM t JOIN u ON (1, 2)');

        self::assertSame(['Operand should contain 1 column(s), not 2.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testEnterDerivesTheConditionWithTheOperandsBeforeExtension(): void
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
        $operation = $semantics->analyze('SELECT 1 FROM t LEFT JOIN u ON u.c = 1', [$t, $u]);
        $select = $operation->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;

        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(JoinedTable::class, $select->from);
        self::assertNotNull($select->from->on);
        self::assertInstanceOf(Comparison::class, $select->from->on);
        self::assertSame(Nullability::NotNull, $operation->facts->scalar($select->from->on->left)->nullability);
    }
}
