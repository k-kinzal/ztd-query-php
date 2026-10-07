<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Grouping;
use MySqlMemory\Plan\Path\Source\SingleRow;
use MySqlMemory\Plan\Path\Transform\Aggregate as AggregatePath;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(Grouping::class)]
#[Small]
final class GroupingTest extends TestCase
{
    public function testPlanLeavesABlockWithoutGroupingOrAggregatesUngrouped(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 + 1');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $input = new SingleRow();
        $scope = new Scope();

        [$path, $evaluation] = (new Grouping($planner))->plan($statement, $input, $scope);

        self::assertSame($input, $path);
        self::assertSame($scope, $evaluation);
    }

    public function testPlanGroupsByTheGroupByExpressionsAndFoldsEachAggregate(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $operation = $session->analyze('SELECT a, COUNT(*), SUM(b) FROM t GROUP BY a');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $scope = new Scope();
        $input = $planner->relations->plan($statement->from, $scope);

        [$path, $evaluation] = (new Grouping($planner))->plan($statement, $input, $scope);

        self::assertInstanceOf(AggregatePath::class, $path);
        self::assertSame($input, $path->input);
        self::assertCount(1, $path->groups);
        self::assertSame([AggregateFunction::Count, AggregateFunction::Sum], array_map(static fn ($accumulation) => $accumulation->function, $path->aggregates));
        self::assertFalse($path->rollup);
        self::assertNotSame($scope, $evaluation);
        self::assertSame(4, $path->width());
    }

    public function testPlanGroupsTheWholeInputForAnAggregateWithoutGroupBy(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $result = $session->query('SELECT COUNT(*), MAX(a) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', null]], $result->rows);
    }

    public function testCollectFindsTheAggregatesOfTheBlockOutsideSubqueriesAndWindows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT COUNT(*), (SELECT MAX(a) FROM t), SUM(a) OVER () FROM t HAVING MIN(a) > 0 ORDER BY GROUP_CONCAT(a)');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $found = (new Grouping($planner))->collect($statement);

        self::assertSame([Aggregate::class, Aggregate::class, GroupConcat::class], array_map(static fn ($node): string => $node::class, $found));
        self::assertSame(['COUNT', 'MIN', 'GROUP_CONCAT'], array_map(static fn ($node): string => $node instanceof Aggregate ? $node->function->value : 'GROUP_CONCAT', $found));
    }

    public function testAccumulationCompilesAnAggregateWithItsArgumentsAndQuantifier(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT COUNT(DISTINCT a) FROM t');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $scope = new Scope();
        $planner->relations->plan($statement->from, $scope);
        $grouping = new Grouping($planner);

        $accumulation = $grouping->accumulation($grouping->collect($statement)[0], $scope);

        self::assertSame(AggregateFunction::Count, $accumulation->function);
        self::assertCount(1, $accumulation->arguments);
        self::assertTrue($accumulation->distinct);
        self::assertSame(0, $accumulation->limit);
    }

    public function testAccumulationReadsTheSeparatorOrderAndGroupConcatMaxLen(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('SET SESSION group_concat_max_len = 10');
        $operation = $session->analyze("SELECT GROUP_CONCAT(a ORDER BY a DESC SEPARATOR '-') FROM t");
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $scope = new Scope();
        $planner->relations->plan($statement->from, $scope);
        $grouping = new Grouping($planner);

        $accumulation = $grouping->accumulation($grouping->collect($statement)[0], $scope);

        self::assertNull($accumulation->function);
        self::assertSame('-', $accumulation->separator);
        self::assertSame(10, $accumulation->limit);
        self::assertCount(1, $accumulation->order);
        self::assertTrue($accumulation->order[0][1]);
    }

    public function testAccumulationJoinsGroupConcatValuesWithACommaByDefault(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (2), (1), (3)');
        $result = $session->query('SELECT GROUP_CONCAT(a ORDER BY a) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1,2,3']], $result->rows);
    }
}
