<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Window;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Window\Resolution;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Resolution::class)]
#[Small]
final class ResolutionTest extends TestCase
{
    public function testResolveListsTheNamedWindowsFirstAndGivesEachItsCalls(): void
    {
        $session = (new Instance())->connect();
        $select = $session->analyze('SELECT ROW_NUMBER() OVER (ORDER BY 2 + 0), RANK() OVER w FROM (SELECT 1 a) t WINDOW w AS (PARTITION BY a), v AS (w ORDER BY a)')->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);
        self::assertInstanceOf(SelectExpression::class, $select->items[1]);
        self::assertInstanceOf(WindowFunction::class, $select->items[0]->expression);
        self::assertInstanceOf(WindowFunction::class, $select->items[1]->expression);
        $windows = (new Resolution())->resolve($select, [$select->items[0]->expression, $select->items[1]->expression]);

        self::assertSame(['w', 'v', '<unnamed window>'], array_map(static fn ($window): string => $window->name, $windows));
        self::assertSame([[$select->items[1]->expression], [], [$select->items[0]->expression]], array_map(static fn ($window): array => $window->calls, $windows));
        self::assertSame([1, 1], [count($windows[1]->partition), count($windows[1]->order)]);
    }

    public function testSpecificationAnswersAParenthesizedWindow(): void
    {
        $window = new WindowSpec();

        self::assertSame($window, (new Resolution())->specification($window));
    }

    public function testDefinitionRefusesAWindowTheBlockDoesNotDefine(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3579);
        $this->expectExceptionMessage("Window name 'w' is not defined.");

        (new Resolution())->definition(new Name('w'));
    }

    public function testAcyclicRefusesWindowsThatRefineEachOther(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3580);
        $this->expectExceptionMessage('There is a circularity in the window dependency graph.');

        $session->query('SELECT 1 WINDOW w AS (v), v AS (w)');
    }

    public function testItemsRefusesAnIntegerBeforeAWindowFunctionOfALaterWindow(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3592);
        $this->expectExceptionMessage("Window 'v': ORDER BY or PARTITION BY uses legacy position indication which is not supported, use expression.");

        $session->query('SELECT 1 WINDOW w AS (ROWS BETWEEN 1 FOLLOWING AND 1 PRECEDING), v AS (ORDER BY 1)');
    }

    public function testItemsRefusesAWindowFunctionInTheWindow(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3595);
        $this->expectExceptionMessage("You cannot nest a window function in the specification of window '<unnamed window>'.");

        $session->query('SELECT SUM(1) OVER (PARTITION BY SUM(1) OVER ())');
    }

    public function testInheritanceRefusesOrderingBeforeTheFrameOfTheWindowRefined(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3583);
        $this->expectExceptionMessage("Window 'y' cannot inherit 'z' since both contain an ORDER BY clause.");

        $session->query('SELECT 1 WINDOW y AS (z ORDER BY 1 + 0), z AS (ORDER BY 2 + 0 ROWS CURRENT ROW)');
    }

    public function testInheritanceRefusesPartitioningInAWindowThatRefinesAnother(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3581);
        $this->expectExceptionMessage('A window which depends on another cannot define partitioning.');

        $session->query('SELECT 1 WINDOW y AS (z PARTITION BY 1 + 0), z AS ()');
    }

    public function testInheritanceRefusesRefiningAWindowWithAFrame(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3582);
        $this->expectExceptionMessage("Window 'z' has a frame definition, so cannot be referenced by another window.");

        $session->query('SELECT 1 WINDOW y AS (z), z AS (ROWS CURRENT ROW)');
    }

    public function testPartitionAnswersThePartitioningOfTheWindowRefined(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE u (id INT, p INT)');
        $session->query('INSERT INTO u VALUES (1, 1), (2, 2), (3, 1)');

        $result = $session->query('SELECT id, ROW_NUMBER() OVER (w ORDER BY id DESC) FROM u WINDOW w AS (PARTITION BY p)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '1'], ['1', '2'], ['2', '1']], $result->rows);
    }

    public function testOrderAnswersTheOrderingOfTheWindowRefinedWhenTheWindowHasNone(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE u (id INT)');
        $session->query('INSERT INTO u VALUES (1), (2), (3)');

        $result = $session->query('SELECT id, ROW_NUMBER() OVER (w) FROM u WINDOW w AS (ORDER BY id DESC)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '1'], ['2', '2'], ['1', '3']], $result->rows);
    }

    public function testNamedAnswersTheNameTheServerGivesAWindowFunction(): void
    {
        self::assertSame(['ntile', 'std'], [Resolution::named(new WindowFunction(WindowFunctionKind::Tile, [new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')], new Name('w'))), Resolution::named(new Aggregate(AggregateFunction::StandardDeviation, [new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')], false, false, new Name('w')))]);
    }
}
