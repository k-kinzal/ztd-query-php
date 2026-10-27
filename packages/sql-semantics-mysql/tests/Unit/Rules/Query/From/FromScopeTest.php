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

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Name\MissingTableQualifier::class, $operation->diagnostics()[0]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerLegacyReleases')]
    public function testDerivedKeepsRoutineVariablesAndTriggerRows(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $procedure = $semantics->analyze('CREATE PROCEDURE p(IN x INT) BEGIN DECLARE y INT DEFAULT 3; SELECT * FROM (SELECT x,y) d; END');
        $trigger = $semantics->analyze('CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET NEW.a=(SELECT v FROM (SELECT NEW.a+OLD.a AS v) d)', [$table]);

        self::assertSame([], $procedure->diagnostics());
        self::assertSame([], $trigger->diagnostics());
        self::assertInstanceOf(MissingColumn::class, $semantics->analyze('CREATE TRIGGER bad BEFORE DELETE ON t FOR EACH ROW SET @v=(SELECT x FROM (SELECT NEW.a AS x) d)', [$table])->diagnostics()[0]);
    }

    public function testDerivedRetainsModernOuterQueryScopes(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $environment = new \SqlSemantics\Resolution\Environment($semantics->context());

        self::assertSame($environment, (new FromScope())->derived($environment));
        self::assertSame([], $semantics->analyze('SELECT (SELECT x FROM (SELECT t.a AS x) d) FROM t', [$table])->diagnostics());
    }

    public function testUniqueRetainsTheLaterRelationEvenWhenIdentifierValuesAreShared(): void
    {
        $name = new Name('t');
        $first = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName($name));
        $second = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName($name));
        $query = new Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new \SqlSemantics\Platform\MySql\Statement\Relation\TableList([$first, $second]));
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context());
        $derivation->query($query, $derivation->environment());
        $problems = array_values(array_filter($derivation->facts()->diagnostics, static fn ($problem): bool => $problem instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable));
        self::assertCount(1, $problems);
        self::assertSame($second, $problems[0]->relation);
        self::assertSame($name, $problems[0]->alias);
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
        self::assertInstanceOf(MissingColumn::class, $wrong->diagnostics()[0]);
    }

    public function testUniqueReportsTheFirstNameTwoRelationsOfOneDatabaseTake(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $messages = static fn (string $sql): array => array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze($sql, $semantics->context(null, false, new SearchPath('fz')))->diagnostics());

        self::assertSame(["Not unique table/alias: 't2'."], $messages('SELECT 1 FROM fz.t2 JOIN t3 AS t2'));
        self::assertSame(["Not unique table/alias: 't'."], $messages('SELECT 1 FROM fz.t, t'));

        self::assertSame([], $messages('SELECT 1 FROM d.t, e.t'));
        self::assertSame([], $messages('SELECT 1 FROM mysql.user, fz.t1 user'));
    }

    public function testEnterReportsARowAsTheJoinCondition(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('Operand should contain 1 column(s), not 2.');
        (new Semantics(Dialect::MySql))->analyze('SELECT 1 FROM t JOIN u ON (1, 2)');
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
        $select = $operation;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;

        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(JoinedTable::class, $select->from);
        self::assertNotNull($select->from->on);
        self::assertInstanceOf(Comparison::class, $select->from->on);
        self::assertSame(Nullability::NotNull, $select->from->on->left->scalarInformation()->nullability);
    }
}
