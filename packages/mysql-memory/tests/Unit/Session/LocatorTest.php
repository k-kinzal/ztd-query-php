<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Instance;
use MySqlMemory\Session\Locator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
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

        self::assertSame(['a', 'b', 'c'], array_map(static fn (ColumnUse|OutputOrdinal|FunctionCall $node): string => $node instanceof OutputOrdinal ? (string) $node->position() : $node->name->value, $locator->nodes()));
        self::assertSame([['field list', [1, 0]], ['where clause', [2, 1]], ['order clause', [6, 0, 0]]], array_values($locator->places));
    }

    public function testExpressionLocatesTheOrderByOfATableStatementInTheOrderClause(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $locator = (new Locator())->statement($session->analyze('TABLE t ORDER BY x')->statement);

        self::assertSame(['order clause'], array_column(array_values($locator->places), 0));
    }

    public function testStatementResolvesDerivedTablesFirstAndOnConditionsAfterWhere(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $locator = (new Locator())->statement($session->analyze('SELECT a FROM (SELECT b FROM t) AS s JOIN t AS u ON u.c = 1 WHERE a = 2 GROUP BY a HAVING a > 0')->statement);

        self::assertSame(['b', 'a', 'a', 'c', 'a', 'a'], array_map(static fn (ColumnUse|OutputOrdinal|FunctionCall $node): string => $node instanceof OutputOrdinal ? (string) $node->position() : $node->name->value, $locator->nodes()));
        self::assertSame(['field list', 'field list', 'where clause', 'on clause', 'group statement', 'having clause'], array_column(array_values($locator->places), 0));
    }

    public function testStatementLocatesTheNamesOfAnUpdate(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $locator = (new Locator())->statement($session->analyze('UPDATE t SET a = b WHERE c = 1 ORDER BY a')->statement);

        self::assertSame(['c', 'a', 'b', 'a'], array_map(static fn (ColumnUse|OutputOrdinal|FunctionCall $node): string => $node instanceof OutputOrdinal ? (string) $node->position() : $node->name->value, $locator->nodes()));
        self::assertSame([['where clause', [1, 1]], ['field list', [2, 0, 0]], ['field list', [2, 1, 0]], ['order clause', [6, 0, 0]]], array_values($locator->places));
    }

    public function testStatementLocatesTheNamesOfAnInsertInTheOrderTheServerResolvesThem(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $rows = (new Locator())->statement($session->analyze('INSERT INTO t (a) VALUES (b) ON DUPLICATE KEY UPDATE c = a')->statement);
        $query = (new Locator())->statement($session->analyze('INSERT INTO t (a) SELECT b FROM t ON DUPLICATE KEY UPDATE c = 1')->statement);

        self::assertSame(['a', 'b', 'c', 'a'], array_map(static fn (ColumnUse|OutputOrdinal|FunctionCall $node): string => $node instanceof OutputOrdinal ? (string) $node->position() : $node->name->value, $rows->nodes()));
        self::assertSame(['a', 'c', 'b'], array_map(static fn (ColumnUse|OutputOrdinal|FunctionCall $node): string => $node instanceof OutputOrdinal ? (string) $node->position() : $node->name->value, $query->nodes()));
    }

    public function testAssignmentsLocatesEveryColumnBeforeEveryValue(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $update = $session->analyze('UPDATE t SET a = b, c = 1')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Update::class, $update);
        $locator = new Locator();
        $locator->assignments($update->assignments, [4]);

        self::assertSame([['field list', [4, 0, 0]], ['field list', [4, 0, 1]], ['field list', [4, 1, 0]]], array_values($locator->places));
    }

    public function testStatementLocatesTheNamesOfADelete(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $locator = (new Locator())->statement($session->analyze('DELETE FROM t WHERE c = 1 ORDER BY a')->statement);

        self::assertSame(['c', 'a'], array_map(static fn (ColumnUse|OutputOrdinal|FunctionCall $node): string => $node instanceof OutputOrdinal ? (string) $node->position() : $node->name->value, $locator->nodes()));
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

    public function testRecordLocatesANameWithoutItsChildren(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $statement = $session->analyze('SELECT a FROM t WHERE b')->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ColumnUse::class, $statement->where);
        $locator = new Locator();
        $locator->record($statement->where, 'having clause', [5]);

        self::assertSame([['having clause', [5]]], array_values($locator->places));
        self::assertSame([$statement->where], $locator->nodes());
        self::assertSame([], $locator->arrays);
    }

    public function testRecordKeepsACastToAnArray(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $statement = $session->analyze('SELECT 1 FROM DUAL WHERE CAST(1 AS SIGNED ARRAY)')->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast::class, $statement->where);
        $locator = new Locator();
        $locator->record($statement->where, 'where clause', [2]);

        self::assertSame([[$statement->where, 'where clause', [2]]], $locator->arrays);
        self::assertSame([], $locator->places);
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

    public function testNodesAnswersThePositionsAndCallsToo(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $locator = (new Locator())->statement($session->analyze('SELECT f(a) FROM t GROUP BY 1')->statement);

        self::assertSame([FunctionCall::class, ColumnUse::class, OutputOrdinal::class], array_map(static fn (object $node): string => $node::class, $locator->nodes()));
        self::assertSame(['field list', 'field list', 'group statement'], array_map(static fn (array $place): string => $place[0], array_values($locator->places)));
    }

    public function testClocksAnswersTheClockCallsWithTheirPlaces(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $locator = (new Locator())->statement($session->analyze('SELECT a FROM t WHERE NOW(3)')->statement);

        self::assertSame([[ColumnUse::class], [ClockCall::class]], [array_map(static fn (object $node): string => $node::class, $locator->nodes()), array_map(static fn (object $node): string => $node::class, $locator->clocks())]);
        self::assertSame(['field list', 'where clause'], array_map(static fn (array $place): string => $place[0], array_values($locator->places)));
    }

    public function testStatementLocatesTheSubqueryOfAPredicateBeforeItsOperand(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $locator = (new Locator())->statement($session->analyze('SELECT a IN (SELECT b FROM t), a = ALL (SELECT b FROM t) FROM t')->statement);

        self::assertSame([['IN/ALL/ANY subquery', [1, 0, 1]], ['field list', [1, 0, 0, 1, 0]], ['IN/ALL/ANY subquery', [1, 1, 2]], ['field list', [1, 1, 0, 1, 0]]], array_values($locator->places));
        self::assertCount(2, $locator->predicates);
    }

    public function testEarlyHoldsForAllAndForAnyOtherThanEquality(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $locator = (new Locator())->statement($session->analyze('SELECT 1 IN (TABLE t), 1 = ANY (TABLE t), 1 <> ALL (TABLE t), 1 = ALL (TABLE t), 1 > ANY (TABLE t)')->statement);

        self::assertSame([false, false, false, true, true], array_map(static fn (array $predicate): bool => Locator::early($predicate[0]), $locator->predicates));
    }

    public function testStatementLocatesACastToAnArrayOutsideAFunctionalIndex(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $locator = (new Locator())->statement($session->analyze('SELECT CAST(1 AS SIGNED ARRAY), CAST(2 AS UNSIGNED)')->statement);

        self::assertCount(1, $locator->arrays);
        self::assertSame('field list', $locator->arrays[0][1]);
    }

    public function testSelectLocatesTheQualifiersOfStarsBeforeTheItems(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $locator = (new Locator())->statement($session->analyze('SELECT a, t.* FROM t')->statement);

        self::assertSame([[1, -1]], array_column($locator->wildcards, 1));
    }

    public function testVisitResolvesTheOperandOfInBeforeItsSubqueryWhenAskedTo(): void
    {
        $session = (new Instance('8.0.44', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $statement = $session->analyze('SELECT a IN (SELECT b FROM t) FROM t')->statement;

        self::assertSame([['IN/ALL/ANY subquery', [1, 0, 0]], ['field list', [1, 0, 1, 1, 0]]], array_values((new Locator(true))->statement($statement)->places));
        self::assertSame([['IN/ALL/ANY subquery', [1, 0, 1]], ['field list', [1, 0, 0, 1, 0]]], array_values((new Locator())->statement($statement)->places));
    }

    public function testSelectLocatesTheWindowsABlockNames(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $locator = (new Locator())->statement($session->analyze('SELECT ROW_NUMBER() OVER w FROM t WINDOW w AS (v), v AS ()')->statement);

        self::assertSame([['w', [1, 0]], ['v', [6, PHP_INT_MAX]]], array_map(static fn (array $entry): array => [$entry[0]->value, $entry[1]], $locator->windows));
    }

    public function testRecordLocatesASetOperationAfterItsRightOperand(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $locator = (new Locator())->statement($session->analyze('SELECT 1 UNION SELECT 2')->statement);

        self::assertCount(1, $locator->sets);
        self::assertSame(PHP_INT_MAX, $locator->sets[0][1][count($locator->sets[0][1]) - 1]);
    }

    public function testSelectLocatesAStarWithoutTablesBeforeTheItems(): void
    {
        $session = (new Instance())->connect();
        $locator = (new Locator())->statement($session->analyze('SELECT 1 IN (SELECT *)')->statement);

        self::assertCount(1, $locator->stars);
    }

    public function testSpecificationsLocatesTheNamesOfTheWindowsAfterTheOrderBy(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $locator = (new Locator())->statement($session->analyze('SELECT RANK() OVER (ORDER BY a) FROM t WINDOW w AS (PARTITION BY c) ORDER BY b')->statement);

        self::assertSame([['order clause', [6, 0, 0]], ['window partition by', [6, PHP_INT_MAX, 0, 0, 0, 0]], ['window order by', [6, PHP_INT_MAX, 1, 1, 0, 0]]], array_values($locator->places));
    }

    public function testWindowedRecordsACallAndTheWindowItNames(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $statement = $session->analyze('SELECT ROW_NUMBER() OVER w FROM t WINDOW w AS ()')->statement;
        self::assertInstanceOf(Select::class, $statement);
        $item = $statement->items[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression::class, $item);
        $locator = new Locator();
        $locator->windowed($item->expression, 'field list', [1, 0]);
        $locator->windowed($statement, 'field list', []);

        self::assertSame([[['field list', [1, 0]]], [['w', [1, 0]]]], [array_map(static fn (array $entry): array => [$entry[1], $entry[2]], $locator->functions), array_map(static fn (array $entry): array => [$entry[0]->value, $entry[1]], $locator->windows)]);
    }
}
