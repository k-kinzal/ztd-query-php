<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Window;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\Window\Windowing;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Windowing::class)]
#[Small]
final class WindowingTest extends TestCase
{
    public function testPlanComputesTheWindowsOneAfterTheOtherNamedFirst(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE u (id INT, o INT)');
        $session->query('INSERT INTO u VALUES (1, 3), (2, 3), (3, 1), (4, 2)');

        $result = $session->query('SELECT id, ROW_NUMBER() OVER (ORDER BY id DESC), ROW_NUMBER() OVER w FROM u WINDOW w AS (ORDER BY o)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['4', '1', '2'], ['3', '2', '1'], ['2', '3', '4'], ['1', '4', '3']], $result->rows);
    }

    public function testCallsRefusesAWindowFunctionInTheArgumentOfAnother(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3593);
        $this->expectExceptionMessage("You cannot use the window function 'rank' in this context.'");

        $session->query('SELECT LAG(RANK() OVER ()) OVER ()');
    }

    public function testRootsAreTheSelectListAndTheOrderBy(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1, 2 FROM (SELECT 3) t WHERE 4 ORDER BY 5');
        self::assertInstanceOf(Select::class, $operation->statement);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);

        self::assertCount(3, (new Windowing($planner))->roots($operation->statement));
    }

    public function testWindowedHoldsForAnAggregateWithAWindowOnly(): void
    {
        self::assertSame([true, false], [Windowing::windowed(new Aggregate(AggregateFunction::Sum, [new NumberLiteral('1')], false, false, new Name('w'))), Windowing::windowed(new Aggregate(AggregateFunction::Sum, [new NumberLiteral('1')]))]);
    }

    public function testPushesSortsBeforeWindowsThatDoNotSort(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE u (id INT)');
        $session->query('INSERT INTO u VALUES (1), (2), (3)');

        $result = $session->query('SELECT id, ROW_NUMBER() OVER () FROM u ORDER BY id DESC')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '1'], ['2', '2'], ['1', '3']], $result->rows);
        $result2 = $session->query('SELECT id, ROW_NUMBER() OVER () r FROM u ORDER BY r DESC')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['3', '3'], ['2', '2'], ['1', '1']], $result2->rows);
    }

    public function testReadsFindsAWindowFunctionThroughAnAlias(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE u (id INT)');
        $operation = $session->analyze('SELECT id, RANK() OVER () r FROM u ORDER BY r + 1, id');
        self::assertInstanceOf(Select::class, $operation->statement);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);

        self::assertSame([true, false], [(new Windowing($planner))->reads($operation->statement->orderBy[0]->expression), (new Windowing($planner))->reads($operation->statement->orderBy[1]->expression)]);
    }

    public function testOrderingLeavesOutAKeyConstantForTheStatement(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 FROM (SELECT 2 a) t ORDER BY 3 + 0, a DESC');
        self::assertInstanceOf(Select::class, $operation->statement);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        self::assertNotNull($operation->statement->from);
        $scope = new Scope();
        $planner->relations->plan($operation->statement->from, $scope);

        self::assertSame([true], array_column((new Windowing($planner))->ordering($operation->statement, $scope), 1));
    }
}
