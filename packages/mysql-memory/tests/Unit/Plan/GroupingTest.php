<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Error\SqlError;
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
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

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

    public function testPlanRefusesGroupByCubeWithoutASecondaryEngine(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3889);
        $this->expectExceptionMessage('Secondary engine operation failed. Reason: "No secondary engine defined for at least one of the query tables".');

        $session->query('SELECT a FROM t GROUP BY CUBE(a)');
    }

    public function testPlanAddsTheSuperAggregateRowsWithRollup(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b VARCHAR(10) NOT NULL, c INT NOT NULL)');
        $session->query("INSERT INTO t VALUES (1, 'x', 10), (1, 'y', 20), (2, 'x', 30), (NULL, 'z', 5), (2, 'x', 7)");
        $result = $session->query('SELECT a, b, SUM(c), a + 1 FROM t GROUP BY a, b WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(
            [[null, 'z', '5', null], [null, null, '5', null], ['1', 'x', '10', '2'], ['1', 'y', '20', '2'], ['1', null, '30', '2'], ['2', 'x', '37', '3'], ['2', null, '37', '3'], [null, null, '72', null]],
            $result->rows,
        );
    }

    public function testRollupReadsAGroupingExpressionFromTheGroupingValuesOfTheRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (1), (2)');
        $result = $session->query('SELECT t.a + 1, COUNT(*), GROUPING(a + 1) FROM t GROUP BY a + 1 WITH ROLLUP HAVING COUNT(*) > 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '2', '0'], [null, '3', '1']], $result->rows);
    }

    public function testRollupRefusesAGroupingArgumentThatIsNotGrouped(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');

        $this->expectExceptionMessage('Argument #1 of GROUPING function is not in GROUP BY');

        $session->query('SELECT a, GROUPING(b) FROM t GROUP BY a WITH ROLLUP');
    }

    public function testRollsTellsWhetherAFieldIsAGroupingExpression(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $operation = $session->analyze('SELECT a + 0 AS x, b, t.* FROM t GROUP BY x, b WITH ROLLUP');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $grouping = new Grouping($planner);

        self::assertSame([true, true, false, true], [$grouping->rolls($statement, $operation->field(0)), $grouping->rolls($statement, $operation->field(1)), $grouping->rolls($statement, $operation->field(2)), $grouping->rolls($statement, $operation->field(3))]);
    }

    public function testSameColumnTellsWhetherANameResolvesToTheColumnOfAResolution(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $operation = $session->analyze('SELECT t.a, a, b FROM t');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $first = $operation->facts->scalar($operation->field(0)->expression ?? new ColumnUse(new Name('a')))->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $first);
        $second = $operation->field(1)->expression;
        $third = $operation->field(2)->expression;
        self::assertInstanceOf(ColumnUse::class, $second);
        self::assertInstanceOf(ColumnUse::class, $third);

        self::assertSame([true, false], [(new Grouping($planner))->sameColumn($first, $second), (new Grouping($planner))->sameColumn($first, $third)]);
    }

    public function testColumnAnswersTheSameColumnForEveryUseOfIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT a FROM t GROUP BY a WITH ROLLUP');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $item = $operation->facts->scalar($operation->field(0)->expression ?? new ColumnUse(new Name('a')))->resolution;
        $group = $operation->facts->scalar($statement->groupBy->items[0]->expression ?? new ColumnUse(new Name('a')))->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $item);
        self::assertInstanceOf(ResolvedColumn::class, $group);

        self::assertSame((new Grouping($planner))->column($group), (new Grouping($planner))->column($item));
    }

    public function testOutputGivesAFieldTheTypeItsRowsHave(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b VARCHAR(10) NOT NULL, d DATE)');
        $result = $session->query('SELECT b, d, a FROM t GROUP BY b, d, a WITH ROLLUP')[0];
        $ordered = $session->query('SELECT a FROM t GROUP BY a WITH ROLLUP ORDER BY a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[40, 31, ''], [40, 0, ''], [11, 0, '']], [[$result->columns[0]->length, $result->columns[0]->decimals, $result->columns[0]->table], [$result->columns[1]->length, $result->columns[1]->decimals, $result->columns[1]->table], [$result->columns[2]->length, $result->columns[2]->decimals, $result->columns[2]->table]]);
        self::assertInstanceOf(ResultSet::class, $ordered);
        self::assertSame(Field::LongLong, $ordered->columns[0]->type);
    }

    public function testOutputReadsTheBitsOfABitColumnATemporaryTableHolds(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a BIT(3))');
        $session->query("INSERT INTO t VALUES (b'101')");
        $result = $session->query('SELECT a FROM t GROUP BY a WITH ROLLUP ORDER BY a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null], ['5']], $result->rows);
    }

    public function testArgumentsAnswersTheGroupingExpressionEachArgumentNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('INSERT INTO t VALUES (1, 1)');
        $result = $session->query('SELECT GROUPING(b, a, b) FROM t GROUP BY a, b WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0'], ['5'], ['7']], $result->rows);
    }

    public function testTargetAnswersTheSelectItemAnAliasOrAPositionNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT a + 1 AS x FROM t GROUP BY x, 1, (a)');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $grouping = new Grouping($planner);
        $items = $statement->groupBy->items ?? [];
        self::assertCount(3, $items);

        self::assertSame([$operation->field(0)->expression, $operation->field(0)->expression], [$grouping->target($items[0]->expression), $grouping->target($items[1]->expression)]);
        self::assertInstanceOf(ColumnUse::class, $grouping->target($items[2]->expression));
    }

    public function testSameComparesExpressionsWithoutRegardToParenthesesCaseOrQualifiers(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $operation = $session->analyze('SELECT ABS(t.a + 1), (abs(a + 1)), ABS(b + 1) FROM t');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $grouping = new Grouping($planner);

        self::assertSame([true, false, true], [$grouping->same($operation->field(0)->expression, $operation->field(1)->expression), $grouping->same($operation->field(0)->expression, $operation->field(2)->expression), $grouping->same(1, 1)]);
    }

    public function testPlanLeavesAConstantGroupingExpressionUnevaluated(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (1), (2)');
        $grouped = $session->query('SELECT a, COUNT(*) FROM t GROUP BY a, 1/0 ORDER BY a')[0];
        $warnings = $session->query('SHOW COUNT(*) WARNINGS')[0];
        $empty = $session->query('SELECT COUNT(*) FROM t WHERE a > 5 GROUP BY 1/0')[0];

        self::assertInstanceOf(ResultSet::class, $grouped);
        self::assertSame([['1', '2'], ['2', '1']], $grouped->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['0']], $warnings->rows);
        self::assertInstanceOf(ResultSet::class, $empty);
        self::assertSame([], $empty->rows);
    }

    public function testSamePropertiesComparesEachPropertyAsAnExpression(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $operation = $session->analyze('SELECT t.a + 1, (A + 1), a + 2 FROM t');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $grouping = new Grouping($planner);
        $first = $operation->field(0)->expression;
        $second = $operation->field(1)->expression;
        $third = $operation->field(2)->expression;
        self::assertInstanceOf(Grouped::class, $second);
        self::assertNotNull($first);
        self::assertNotNull($third);

        self::assertSame([true, false], [$grouping->sameProperties($first, $second->operand), $grouping->sameProperties($first, $third)]);
    }
}
