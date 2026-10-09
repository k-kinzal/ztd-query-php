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
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

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

    public function testGroupableRefusesAnAliasOfAWindowFunction(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1056);
        $this->expectExceptionMessage("Can't group on 'r'");

        $session->query('SELECT a, RANK() OVER () r FROM t GROUP BY a, (r)');
    }

    public function testGroupableRefusesAnAggregateReadThroughAnAliasInsideAnExpression(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1056);
        $this->expectExceptionMessage("Can't group on '???'");

        $session->query('SELECT a, SUM(b) s FROM t GROUP BY a, s + 1');
    }

    public function testJsonFoldsJsonAggregatesWithPredicatesAsBooleans(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE s (id INT, j JSON)');
        $session->query("INSERT INTO s VALUES (1, '1'), (2, '\"a\"'), (3, NULL)");
        $result = $session->query('SELECT JSON_ARRAYAGG(j), JSON_OBJECTAGG(id, id > 1), JSON_ARRAYAGG(1.50) FROM s')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['[1, "a", null]', '{"1": false, "2": true, "3": true}', '[1.50, 1.50, 1.50]']], $result->rows);
    }

    public function testSortsAnswersTheGroupsInTheOrderTheyFirstAppear(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, a INT, b INT)');
        $session->query('INSERT INTO t VALUES (1, 3, 10), (2, NULL, 20), (3, 1, 30), (4, 3, 40)');
        $plain = $session->query('SELECT a, SUM(b) FROM t GROUP BY a')[0];
        $concatenated = $session->query('SELECT a, GROUP_CONCAT(b) FROM t GROUP BY a')[0];

        self::assertInstanceOf(ResultSet::class, $plain);
        self::assertInstanceOf(ResultSet::class, $concatenated);
        self::assertSame([[['3', '50'], [null, '20'], ['1', '30']], [[null, '20'], ['1', '30'], ['3', '10,40']]], [$plain->rows, $concatenated->rows]);
    }

    public function testSortsAnswersTheGroupsInOrderInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, a INT)');
        $session->query('INSERT INTO t VALUES (1, 3), (2, NULL), (3, 1)');
        $result = $session->query('SELECT a, COUNT(*) FROM t GROUP BY a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, '1'], ['1', '1'], ['3', '1']], $result->rows);
    }

    public function testIndexedTellsWhetherTheGroupingColumnsLeadAnIndex(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, a INT, b INT, KEY ia (a))');
        $session->query('INSERT INTO t VALUES (1, 3, 10), (2, NULL, 20), (3, 1, 30), (4, 3, 10)');
        $indexed = $session->query('SELECT a, COUNT(*) FROM t GROUP BY a')[0];
        $other = $session->query('SELECT b, COUNT(*) FROM t GROUP BY b')[0];

        self::assertInstanceOf(ResultSet::class, $indexed);
        self::assertInstanceOf(ResultSet::class, $other);
        self::assertSame([[[null, '1'], ['1', '1'], ['3', '2']], [['10', '2'], ['20', '1'], ['30', '1']]], [$indexed->rows, $other->rows]);
    }

    public function testDeclarationsAnswersTheColumnsTheGroupingExpressionsRead(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $operation = $session->analyze('SELECT a, b FROM t GROUP BY (b), a');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $other = $session->analyze('SELECT a FROM t GROUP BY a + 1')->statement;
        self::assertInstanceOf(Select::class, $other);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $grouping = new Grouping(new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary));
        $columns = $session->instance->dictionary->table('d', 't')?->definition->columns ?? [];

        self::assertSame([[$columns[1]->declaration, $columns[0]->declaration], null], [$grouping->declarations($statement), $grouping->declarations($other)]);
    }

    public function testLeadsTellsWhetherTheColumnsLeadAnIndex(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT, c VARCHAR(10), KEY iab (a, b), KEY ic (c(2)))');
        $operation = $session->analyze('SELECT 1');
        $statement = $operation->statement;
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $grouping = new Grouping(new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary));
        $definition = $session->instance->dictionary->table('d', 't')?->definition;
        self::assertNotNull($definition);
        $columns = $definition->columns;

        self::assertSame([true, true, false, false, false], [
            $grouping->leads($definition, [$columns[0]->declaration]),
            $grouping->leads($definition, [$columns[0]->declaration, $columns[1]->declaration]),
            $grouping->leads($definition, [$columns[1]->declaration]),
            $grouping->leads($definition, [$columns[2]->declaration]),
            $grouping->leads($definition, [null]),
        ]);
    }

    public function testAccumulationNamesAJsonAggregateAsTheSourceOfItsValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1)');
        $session->query('SELECT JSON_ARRAYAGG(a) + 0 FROM t');

        self::assertSame([['Warning', 3156, 'Invalid JSON value for CAST to DOUBLE from column json_arrayagg at row 1']], $session->diagnostics->conditions);
    }
}
