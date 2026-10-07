<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Instance;
use MySqlMemory\Session\Locator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(Locator::class)]
#[Small]
final class LocatorTest extends TestCase
{
    public function testStatementLocatesTheNamesOfAQueryBlockByClause(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $locator = (new Locator())->statement($session->analyze('SELECT a FROM t WHERE b = 1 ORDER BY c')->statement);

        self::assertSame(['a', 'b', 'c'], array_map(static fn (ColumnUse $node): string => $node->name->value, $locator->nodes()));
        self::assertSame([['field list', [1, 0]], ['where clause', [2, 1]], ['order clause', [6, 0, 0]]], array_values($locator->places));
    }

    public function testStatementResolvesDerivedTablesFirstAndOnConditionsAfterWhere(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $locator = (new Locator())->statement($session->analyze('SELECT a FROM (SELECT b FROM t) AS s JOIN t AS u ON u.c = 1 WHERE a = 2 GROUP BY a HAVING a > 0')->statement);

        self::assertSame(['b', 'a', 'a', 'c', 'a', 'a'], array_map(static fn (ColumnUse $node): string => $node->name->value, $locator->nodes()));
        self::assertSame(['field list', 'field list', 'where clause', 'on clause', 'group statement', 'having clause'], array_column(array_values($locator->places), 0));
    }

    public function testStatementLocatesTheNamesOfAnUpdate(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $locator = (new Locator())->statement($session->analyze('UPDATE t SET a = b WHERE c = 1 ORDER BY a')->statement);

        self::assertSame(['a', 'b', 'c', 'a'], array_map(static fn (ColumnUse $node): string => $node->name->value, $locator->nodes()));
        self::assertSame([['field list', [1, 0, 0]], ['field list', [1, 0, 1]], ['where clause', [2, 1]], ['order clause', [6, 0, 0]]], array_values($locator->places));
    }

    public function testStatementLocatesTheNamesOfADelete(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $locator = (new Locator())->statement($session->analyze('DELETE FROM t WHERE c = 1 ORDER BY a')->statement);

        self::assertSame(['c', 'a'], array_map(static fn (ColumnUse $node): string => $node->name->value, $locator->nodes()));
        self::assertSame([['where clause', [2, 1]], ['order clause', [6, 0, 0]]], array_values($locator->places));
    }

    public function testStatementLocatesASubqueryWhereItIsWritten(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $locator = (new Locator())->statement($session->analyze('SELECT a, (SELECT b FROM t AS x) FROM t')->statement);

        self::assertSame([['field list', [1, 0]], ['field list', [1, 1, 0, 1, 0]]], array_values($locator->places));
    }

    public function testPrecedesComparesElementByElement(): void
    {
        self::assertTrue(Locator::precedes([1, 2], [1, 3]));
        self::assertFalse(Locator::precedes([1, 3], [1, 2]));
        self::assertTrue(Locator::precedes([0, 9], [1]));
        self::assertFalse(Locator::precedes([2], [1, 5]));
    }

    public function testPrecedesPutsAPrefixFirst(): void
    {
        self::assertTrue(Locator::precedes([1], [1, 0]));
        self::assertFalse(Locator::precedes([1, 0], [1]));
        self::assertTrue(Locator::precedes([], [0]));
        self::assertFalse(Locator::precedes([1, 2], [1, 2]));
    }

    public function testNodesIsEmptyBeforeAStatementIsLocated(): void
    {
        self::assertSame([], (new Locator())->nodes());
    }

    public function testNodesOfAStatementWithoutColumnNames(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $locator = (new Locator())->statement($session->analyze('INSERT INTO t VALUES (1, 2, 3)')->statement);

        self::assertSame([], $locator->nodes());
    }

    public function testPlaceAnswersTheClauseAndOrderOfAName(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $locator = (new Locator())->statement($session->analyze('SELECT a FROM t HAVING b > 0')->statement);

        self::assertSame(['having clause', [5, 1]], $locator->place($locator->nodes()[1]));
    }

    public function testPlaceAnswersNullForANameNotLocated(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $statement = $session->analyze('SELECT a FROM t WHERE b = 1')->statement;
        $located = (new Locator())->statement($statement);

        self::assertNull((new Locator())->place($located->nodes()[0]));
    }

    public function testVisitLocatesTheNamesUnderAValue(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $statement = $session->analyze('SELECT a FROM t WHERE b = c')->statement;
        self::assertInstanceOf(Select::class, $statement);
        $locator = new Locator();
        $locator->visit($statement->where, 'on clause', [9]);

        self::assertSame([['on clause', [9, 1]], ['on clause', [9, 2]]], array_values($locator->places));
    }

    public function testVisitAddsThePositionOfEachMemberOfAList(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $statement = $session->analyze('SELECT a FROM t ORDER BY b, c')->statement;
        self::assertInstanceOf(Select::class, $statement);
        $locator = new Locator();
        $locator->visit($statement->orderBy, 'order clause', []);
        $locator->visit(null, 'order clause', []);

        self::assertSame([['order clause', [0, 0]], ['order clause', [1, 0]]], array_values($locator->places));
    }

    public function testSelectPrefixesTheOrderOfTheBlock(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $statement = $session->analyze('SELECT a FROM t WHERE b = 1 LIMIT 1')->statement;
        self::assertInstanceOf(Select::class, $statement);
        $locator = new Locator();
        $locator->select($statement, [4]);

        self::assertSame([['field list', [4, 1, 0]], ['where clause', [4, 2, 1]]], array_values($locator->places));
    }

    public function testFromLocatesOnlyTheDerivedTables(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $statement = $session->analyze('SELECT a FROM (SELECT b FROM t WHERE c = 1) AS s JOIN t AS u ON u.a = 1')->statement;
        self::assertInstanceOf(Select::class, $statement);
        $locator = new Locator();
        $locator->from($statement->from, [7]);

        self::assertSame(['b', 'c'], array_map(static fn (ColumnUse $node): string => $node->name->value, $locator->nodes()));
        self::assertSame(['field list', 'where clause'], array_column(array_values($locator->places), 0));
    }

    public function testFromLocatesNothingInABaseTable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $statement = $session->analyze('SELECT a FROM t')->statement;
        self::assertInstanceOf(Select::class, $statement);
        $locator = new Locator();
        $locator->from($statement->from, []);
        $locator->from(null, []);

        self::assertSame([], $locator->places);
    }

    public function testConditionsLocatesTheOnConditionsOfTheJoins(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $statement = $session->analyze('SELECT t.a FROM t JOIN t AS u ON u.c = 1 WHERE t.b = 1')->statement;
        self::assertInstanceOf(Select::class, $statement);
        $locator = new Locator();
        $locator->conditions($statement->from, [3]);

        self::assertSame(['c'], array_map(static fn (ColumnUse $node): string => $node->name->value, $locator->nodes()));
        self::assertSame([['on clause', [3, 2, 1]]], array_values($locator->places));
    }

    public function testConditionsLocatesNothingWithoutAJoin(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $statement = $session->analyze('SELECT a FROM (SELECT a FROM t AS x JOIN t AS y ON x.b = y.b) AS s')->statement;
        self::assertInstanceOf(Select::class, $statement);
        $locator = new Locator();
        $locator->conditions($statement->from, []);
        $locator->conditions(null, []);

        self::assertSame([], $locator->places);
    }
}
