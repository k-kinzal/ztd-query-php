<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Command\Output;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Path\Combine\NestedLoopJoin;
use MySqlMemory\Plan\Path\JoinKind;
use MySqlMemory\Plan\Path\Source\SingleRow;
use MySqlMemory\Plan\Path\Source\TableScan;
use MySqlMemory\Plan\Path\Transform\Materialize;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\Relations;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Relations::class)]
#[Small]
final class RelationsTest extends TestCase
{
    public function testPlanReadsAStoredTableByAScanAndPlacesItInTheScope(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $operation = $session->analyze('SELECT * FROM t');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $scope = new Scope();

        $path = $planner->relations->plan($statement->from, $scope);

        self::assertInstanceOf(TableScan::class, $path);
        self::assertSame([spl_object_id($statement->from) => ['a', 'b']], $scope->names);
        self::assertSame([spl_object_id($statement->from) => $path], $scope->scans);
    }

    public function testPlanReadsDualAsOneRow(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 FROM DUAL');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        self::assertInstanceOf(SingleRow::class, $planner->relations->plan($statement->from, new Scope()));
    }

    public function testPlanReadsTheRelationsInsideParentheses(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE TABLE u (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2)');
        $session->query('INSERT INTO u VALUES (2), (3)');
        $result = $session->query('SELECT x.a FROM (t AS x JOIN u AS y ON x.a = y.a)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
    }

    public function testPlanReadsTheRelationsOfAnOdbcJoin(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (a INT); INSERT INTO t VALUES (1), (2); INSERT INTO u VALUES (2)');
        $result = $session->query('SELECT t.a, u.a FROM { OJ t LEFT JOIN u ON t.a = u.a } ORDER BY t.a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', null], ['2', '2']], $result->rows);
    }

    public function testTableRaisesForATableThatDoesNotExist(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage("Table 'd.nowhere' doesn't exist");

        $session->query('SELECT * FROM nowhere');
    }

    public function testTableReadsACommonTableExpressionAsAMaterializedQuery(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('WITH c AS (SELECT 1 AS n) SELECT n FROM c AS x');
        $statement = $operation->statement;
        self::assertInstanceOf(QueryExpression::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $plan = $planner->query($statement, null);
        $result = (new Output())->result($plan, $context);

        self::assertSame([['1']], $result->rows);
        self::assertSame('x', $result->columns[0]->table);
    }

    public function testTableReadsATableOfAnotherDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE DATABASE e');
        $session->query('USE d');
        $session->query('CREATE TABLE e.t (a INT)');
        $session->query('INSERT INTO e.t VALUES (5)');
        $result = $session->query('SELECT a FROM e.t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['5']], $result->rows);
        self::assertSame('e', $result->columns[0]->schema);
    }

    public function testDerivedNamesTheColumnsByItsColumnList(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT * FROM (SELECT 1, 2) AS x (p, q)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['p', 'q'], array_map(static fn ($column): string => $column->name, $result->columns));
        self::assertSame([['1', '2']], $result->rows);
    }

    public function testDerivedReadsTheRowsOfItsQueryThroughAMaterialization(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT * FROM (SELECT a FROM t) AS x');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $from = $statement->from;
        self::assertInstanceOf(DerivedTable::class, $from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $scope = new Scope();

        $path = $planner->relations->derived($from, $scope);

        self::assertInstanceOf(Materialize::class, $path);
        self::assertFalse($path->lateral);
        self::assertSame([spl_object_id($from) => 'x'], $scope->derived);
        self::assertSame([spl_object_id($from) => ['a']], $scope->names);
    }

    public function testDerivedLetsALateralTableReadTheRowsBeforeIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2)');
        $result = $session->query('SELECT t.a, l.m FROM t, LATERAL (SELECT t.a * 10 AS m) AS l ORDER BY t.a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '10'], ['2', '20']], $result->rows);
    }

    public function testShapedTypesTheColumnsOfADerivedTableAsTheQueryResolvesThem(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT NOT NULL, c VARCHAR(5))');
        $operation = $session->analyze('SELECT * FROM (SELECT a, c FROM t) AS x');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $domains = $planner->relations->shaped($statement->from, [Domain::null(), Domain::null()]);

        self::assertSame([Field::Long, Field::VarString], array_map(static fn (Domain $domain): Field => $domain->field, $domains));
        self::assertSame([false, true], array_map(static fn (Domain $domain): bool => $domain->nullable, $domains));
        self::assertSame(5, $domains[1]->length);
    }

    public function testMergeableTellsWhetherTheServerMergesADerivedQuery(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $plain = $session->analyze('SELECT a FROM t')->statement;
        $distinct = $session->analyze('SELECT DISTINCT a FROM t')->statement;
        $grouped = $session->analyze('SELECT a FROM t GROUP BY a')->statement;
        $limited = $session->analyze('SELECT a FROM t LIMIT 1')->statement;
        self::assertInstanceOf(Select::class, $plain);
        self::assertInstanceOf(Select::class, $distinct);
        self::assertInstanceOf(Select::class, $grouped);
        self::assertInstanceOf(Select::class, $limited);
        $operation = $session->analyze('SELECT 1');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $relations = new Relations(new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary));

        self::assertSame([true, false, false, false], [$relations->mergeable($plain), $relations->mergeable($distinct), $relations->mergeable($grouped), $relations->mergeable($limited)]);
    }

    public function testListPairsEveryRowOfEachMember(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE TABLE u (b INT)');
        $session->query('INSERT INTO t VALUES (1), (2)');
        $session->query('INSERT INTO u VALUES (3), (4)');
        $operation = $session->analyze('SELECT * FROM t, u');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $from = $statement->from;
        self::assertInstanceOf(TableList::class, $from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $path = $planner->relations->list($from, new Scope());

        self::assertInstanceOf(NestedLoopJoin::class, $path);
        self::assertSame(JoinKind::Inner, $path->kind);
        self::assertNull($path->condition);
        self::assertSame(2, $path->width());
        $result = $session->query('SELECT a, b FROM t, u ORDER BY a, b')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '3'], ['1', '4'], ['2', '3'], ['2', '4']], $result->rows);
    }

    public function testListJoinsThreeMembersFromLeftToRight(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT * FROM (SELECT 1 AS a) AS x, (SELECT 2 AS b) AS y, (SELECT 3 AS c) AS z')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '2', '3']], $result->rows);
    }

    public function testLateralTellsWhetherAMemberReadsTheMembersBeforeIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT * FROM t, LATERAL (SELECT t.a) AS l, (SELECT 1) AS m');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $from = $statement->from;
        self::assertInstanceOf(TableList::class, $from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $relations = new Relations(new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary));

        self::assertSame([false, true, false], array_map(static fn ($member): bool => $relations->lateral($member), $from->members));
    }

    public function testJoinKeepsTheUnmatchedLeftRowsOfALeftJoin(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE TABLE u (a INT, c VARCHAR(1))');
        $session->query('INSERT INTO t VALUES (1, 10), (2, 5), (3, 7)');
        $session->query("INSERT INTO u VALUES (1, 'x'), (3, 'y'), (4, 'z')");
        $result = $session->query('SELECT t.b, u.c FROM t LEFT JOIN u ON t.a = u.a ORDER BY t.b')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['5', null], ['7', 'y'], ['10', 'x']], $result->rows);
    }

    public function testJoinKeepsTheUnmatchedRightRowsOfARightJoin(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE TABLE u (a INT, c VARCHAR(1))');
        $session->query('INSERT INTO t VALUES (1, 10), (2, 5), (3, 7)');
        $session->query("INSERT INTO u VALUES (1, 'x'), (3, 'y'), (4, 'z')");
        $result = $session->query('SELECT t.b, u.c FROM t RIGHT JOIN u ON t.a = u.a ORDER BY u.c')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['10', 'x'], ['7', 'y'], [null, 'z']], $result->rows);
    }

    public function testJoinComparesTheColumnsNamedByUsing(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE TABLE u (a INT, c VARCHAR(1))');
        $session->query('INSERT INTO t VALUES (1, 10), (2, 5), (3, 7)');
        $session->query("INSERT INTO u VALUES (1, 'x'), (3, 'y'), (4, 'z')");
        $result = $session->query('SELECT * FROM t JOIN u USING (a) ORDER BY b')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['a', 'b', 'c'], array_map(static fn ($column): string => $column->name, $result->columns));
        self::assertSame([['3', '7', 'y'], ['1', '10', 'x']], $result->rows);
    }

    public function testJoinComparesTheCommonColumnsOfANaturalJoin(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('INSERT INTO t VALUES (1, 2), (3, 4)');
        $result = $session->query('SELECT * FROM t NATURAL JOIN (SELECT 2 AS b, 9 AS e) AS q')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['b', 'a', 'e'], array_map(static fn ($column): string => $column->name, $result->columns));
        self::assertSame([['2', '1', '9']], $result->rows);
    }

    public function testCommonAnswersTheNamesBothSidesHaveInTheOrderOfTheLeftSide(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 FROM DUAL');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $relations = new Relations(new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary));
        $scope = new Scope();
        $scope->place($statement, [Domain::integer(), Domain::integer(), Domain::integer()], ['c', 'a', 'B']);
        $scope->place($statement->from, [Domain::integer(), Domain::integer(), Domain::integer()], ['b', 'x', 'C']);

        self::assertSame(['c', 'B'], $relations->common($scope, [spl_object_id($statement)], [spl_object_id($statement->from)]));
    }

    public function testVisibleLeavesOutTheInvisibleColumnsOfAStoredTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT INVISIBLE, c INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $operation = $session->analyze('SELECT 1 FROM DUAL');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $relations = new Relations(new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary));
        $scope = new Scope();
        $scope->place($statement, [Domain::integer(), Domain::integer(), Domain::integer()], ['a', 'b', 'c'], $table->definition);
        $scope->place($statement->from, [Domain::integer()], ['b']);

        self::assertSame(['a', 'c'], $relations->visible($scope, spl_object_id($statement)));
        self::assertSame(['b'], $relations->visible($scope, spl_object_id($statement->from)));
    }

    public function testEqualitiesComparesTheColumnsOfEachNameOnBothSides(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 FROM DUAL');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $relations = new Relations(new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary));
        $scope = new Scope();
        $scope->place($statement, [Domain::integer(), Domain::integer()], ['a', 'b']);
        $scope->place($statement->from, [Domain::integer(), Domain::integer()], ['B', 'A']);

        $condition = $relations->equalities($scope, [spl_object_id($statement)], [spl_object_id($statement->from)], ['a', 'b']);

        self::assertNotNull($condition);
        self::assertSame(1, $condition->evaluate(new Frame($context, [1, 2, 2, 1])));
        self::assertSame(0, $condition->evaluate(new Frame($context, [1, 2, 2, 3])));
        self::assertNull($condition->evaluate(new Frame($context, [1, null, null, 1])));
    }

    public function testEqualitiesAnswersNoConditionForNoNames(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $relations = new Relations(new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary));

        self::assertNull($relations->equalities(new Scope(), [], [], []));
    }

    public function testEqualitiesRaisesForANameOneSideLacks(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 FROM DUAL');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $relations = new Relations(new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary));
        $scope = new Scope();
        $scope->place($statement, [Domain::integer()], ['a']);
        $scope->place($statement->from, [Domain::integer()], ['b']);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $this->expectExceptionMessage("Unknown column 'a' in 'from clause'");

        $relations->equalities($scope, [spl_object_id($statement)], [spl_object_id($statement->from)], ['a']);
    }

    public function testFindAnswersTheColumnOfANameAtItsPositionInTheRow(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 FROM DUAL');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $relations = new Relations(new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary));
        $scope = new Scope();
        $scope->place($statement, [Domain::integer(), Domain::integer()], ['a', 'b']);
        $scope->place($statement->from, [Domain::integer(), Domain::null()], ['c', 'D']);

        $column = $relations->find($scope, [spl_object_id($statement), spl_object_id($statement->from)], 'd');

        self::assertNotNull($column);
        self::assertSame(3, $column->position);
        self::assertSame(Field::Null, $column->domain->field);
        self::assertNull($relations->find($scope, [spl_object_id($statement)], 'c'));
    }
}
