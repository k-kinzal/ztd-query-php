<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Command\Output;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Blocks;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Distinct;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(Blocks::class)]
#[Small]
final class BlocksTest extends TestCase
{
    public function testSelectAppliesWhereGroupingHavingOrderAndLimitInTurn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('INSERT INTO t VALUES (1, 10), (1, 20), (2, 5), (3, 7), (3, 8), (4, 1)');
        $result = $session->query('SELECT a, SUM(b) FROM t WHERE b > 1 GROUP BY a HAVING SUM(b) > 5 ORDER BY a DESC LIMIT 2')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '15'], ['1', '30']], $result->rows);
    }

    public function testSelectPlansTheSortKeysAfterTheSelectList(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $operation = $session->analyze('SELECT DISTINCT a FROM t ORDER BY b DESC LIMIT 3');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $plan = $planner->blocks->select($statement, null);

        self::assertInstanceOf(Limit::class, $plan->root);
        self::assertSame(3, $plan->root->count);
        self::assertInstanceOf(Sort::class, $plan->root->input);
        self::assertSame(1, $plan->root->input->keys[0][0]);
        self::assertTrue($plan->root->input->keys[0][2]);
        self::assertInstanceOf(Distinct::class, $plan->root->input->input);
        self::assertCount(1, $plan->root->input->input->domains);
        self::assertInstanceOf(Project::class, $plan->root->input->input->input);
        self::assertSame(2, $plan->root->width());
        self::assertSame(['a'], $plan->names);
    }

    public function testSelectReadsOneRowWithoutFrom(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1 + 1, NULL')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', null]], $result->rows);
    }

    public function testSelectRemovesDuplicateRowsWithDistinct(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (c VARCHAR(3))');
        $session->query("INSERT INTO t VALUES ('a'), ('b'), ('A'), (NULL), (NULL)");
        $result = $session->query('SELECT DISTINCT c FROM t ORDER BY c')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null], ['a'], ['b']], $result->rows);
    }

    public function testSelectBufferedResultKeepsTheRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (2), (1)');
        $result = $session->query('SELECT SQL_BUFFER_RESULT a FROM t ORDER BY a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['2']], $result->rows);
    }

    public function testSelectDistinctOverJoinedTablesDropsTheKeyFlags(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY)');
        $joined = $session->query('SELECT DISTINCT x.a, t.a FROM t AS x, t')[0];
        $constant = $session->query('SELECT DISTINCT x.a FROM t AS x, t WHERE t.a = 1')[0];

        self::assertInstanceOf(ResultSet::class, $joined);
        self::assertInstanceOf(ResultSet::class, $constant);
        self::assertSame([0, 2], [$joined->columns[0]->flags & 2, $constant->columns[0]->flags & 2]);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerEmptyHoldsForABlockTheServerFindsReadsNoRow(): iterable
    {
        yield 'unknown WHERE' => ['SELECT a FROM t WHERE a = 1 AND NULL', true];
        yield 'false HAVING' => ['SELECT a FROM t HAVING 0', true];
        yield 'no row to send' => ['SELECT a FROM t LIMIT 0', true];
        yield 'false comparison that warns' => ["SELECT a FROM t WHERE 'x' = 1", true];
        yield 'condition on a column' => ['SELECT a FROM t WHERE a = 1', false];
    }

    #[DataProvider('providerEmptyHoldsForABlockTheServerFindsReadsNoRow')]
    public function testEmptyHoldsForABlockTheServerFindsReadsNoRow(string $sql, bool $empty): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY)');
        $operation = $session->analyze($sql);
        self::assertInstanceOf(Select::class, $operation->statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        self::assertSame($empty, (new Blocks($planner))->empty($operation->statement, null));
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testSelectBufferedResultOfNoRowKeepsTheKeyFlags(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY)');
        $buffered = $session->query('SELECT SQL_BUFFER_RESULT a FROM t WHERE 0')[0];

        self::assertInstanceOf(ResultSet::class, $buffered);
        self::assertSame(2, $buffered->columns[0]->flags & 2);
    }

    public function testTableReadsEveryVisibleColumnOfTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT INVISIBLE, c VARCHAR(2))');
        $session->query("INSERT INTO t VALUES (1, 'x')");
        $operation = $session->analyze('TABLE t');
        $statement = $operation->statement;
        self::assertInstanceOf(ExplicitTable::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $plan = $planner->blocks->table($statement, null);
        $result = (new Output())->result($plan, $context);

        self::assertSame(['a', 'c'], $plan->names);
        self::assertSame([['1', 'x']], $result->rows);
        self::assertSame(['t', 't', 'd', 'c'], [$result->columns[1]->table, $result->columns[1]->originalTable, $result->columns[1]->schema, $result->columns[1]->originalName]);
    }

    public function testTableReadsTheRowsOfAView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2)');
        $session->query('CREATE VIEW v (x) AS SELECT a * 10 FROM t');

        $result = $session->query('TABLE v')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['10'], ['20']], $result->rows);
        self::assertSame(['x', 'v', 'v'], [$result->columns[0]->name, $result->columns[0]->table, $result->columns[0]->originalTable]);
    }

    public function testTableRaisesForATableThatDoesNotExist(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage("Table 'd.nowhere' doesn't exist");

        $session->query('TABLE nowhere');
    }

    public function testLimitSkipsTheOffsetAndKeepsTheCount(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2), (3), (4), (5)');
        $comma = $session->query('SELECT a FROM t ORDER BY a LIMIT 1, 2')[0];
        $offset = $session->query('SELECT a FROM t ORDER BY a LIMIT 2 OFFSET 3')[0];

        self::assertInstanceOf(ResultSet::class, $comma);
        self::assertInstanceOf(ResultSet::class, $offset);
        self::assertSame([['2'], ['3']], $comma->rows);
        self::assertSame([['4'], ['5']], $offset->rows);
    }

    public function testLimitAnswersThePathItselfWithoutALimitClause(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $path = new ZeroRows(1);

        self::assertSame($path, $planner->blocks->limit($path, null, null));
    }

    public function testBoundEvaluatesTheLimitValues(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 LIMIT 7, 0');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $path = $planner->blocks->limit(new ZeroRows(1), $statement->limit, null);

        self::assertInstanceOf(Limit::class, $path);
        self::assertSame([0, 7], [$path->count, $path->offset]);
    }

    public function testBoundReadsAPreparedParameter(): void
    {
        $session = (new Instance())->connect();
        $result = $session->run('SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 LIMIT ?', [[2, Domain::integer()]], true)[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['2']], $result->rows);
    }

    public function testNameIsTheAliasOrTheExpressionAsWritten(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT a AS c, a  +  1, a FROM t');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $fields = $planner->compiler->facts->query($statement)->projection;
        self::assertInstanceOf(Field::class, $fields[0]);
        self::assertInstanceOf(Field::class, $fields[1]);
        self::assertInstanceOf(Field::class, $fields[2]);

        self::assertSame(['c', 'a  +  1', 'a'], [$planner->blocks->name($fields[0]), $planner->blocks->name($fields[1]), $planner->blocks->name($fields[2])]);
    }

    public function testNameReadsALatin1LiteralInTheSystemCharacterSet(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT _latin1'é', (_latin1'  x€')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['Ã©', 'xâ‚¬'], [$result->columns[0]->name, $result->columns[1]->name]);
    }

    public function testSelectReportsNoBaseColumnForARollupItem(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b VARCHAR(10) NOT NULL, x TEXT)');
        $result = $session->query('SET sql_mode = ""; SELECT a, b, COUNT(*) FROM t GROUP BY a WITH ROLLUP')[1];
        $ordered = $session->query('SELECT x FROM t GROUP BY x WITH ROLLUP ORDER BY x')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['', '', true], ['b', 't', false]], [[$result->columns[0]->originalName, $result->columns[0]->table, ($result->columns[0]->flags & 1) === 0], [$result->columns[1]->originalName, $result->columns[1]->table, ($result->columns[1]->flags & 1) === 0]]);
        self::assertInstanceOf(ResultSet::class, $ordered);
        self::assertSame(['', 16], [$ordered->columns[0]->table, $ordered->columns[0]->flags & 16]);
    }

    public function testOriginReportsTheBaseColumnThroughTheAliasOfItsTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $result = $session->query('SELECT x.a AS c, a + 1 FROM t AS x')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['c', 'a', 'x', 't', 'd'], [$result->columns[0]->name, $result->columns[0]->originalName, $result->columns[0]->table, $result->columns[0]->originalTable, $result->columns[0]->schema]);
        self::assertSame(['', '', '', ''], [$result->columns[1]->originalName, $result->columns[1]->table, $result->columns[1]->originalTable, $result->columns[1]->schema]);
    }

    public function testOriginReportsTheAliasOfADerivedTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $result = $session->query('SELECT x.a FROM (SELECT a FROM t) AS x')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['a', 'x'], [$result->columns[0]->name, $result->columns[0]->table]);
    }

    public function testWhereEvaluatesAConstantConjunctOnceBeforeAnyRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE TABLE e (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2), (3)');
        $empty = $session->query("SELECT a FROM e WHERE 'x' + 0 = 0")[0];
        $once = $session->query('SHOW WARNINGS')[0];
        $result = $session->query("SELECT a FROM t WHERE a > 1 AND ('y' + 0 = 0)")[0];
        $conjunct = $session->query('SHOW WARNINGS')[0];
        $impossible = $session->query("SELECT 'z' + 0 FROM t WHERE 'w' + 0 = 1")[0];
        $unread = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $empty);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $impossible);
        self::assertSame([[], [['2'], ['3']], []], [$empty->rows, $result->rows, $impossible->rows]);
        self::assertInstanceOf(ResultSet::class, $once);
        self::assertInstanceOf(ResultSet::class, $conjunct);
        self::assertInstanceOf(ResultSet::class, $unread);
        self::assertSame([["Truncated incorrect DOUBLE value: 'x'"], ["Truncated incorrect DOUBLE value: 'y'"], ["Truncated incorrect DOUBLE value: 'w'"]], [array_column($once->rows, 2), array_column($conjunct->rows, 2), array_column($unread->rows, 2)]);
    }

    public function testConjunctsSplitsTheTopLevelAnds(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 FROM DUAL WHERE (1 AND (2 && 3)) AND (4 OR 5)');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->where);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        self::assertCount(4, $planner->blocks->conjuncts($statement->where));
    }

    public function testSelectSortsByASelectItemAndIgnoresAConstantKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (2), (1), (3)');
        $result = $session->query("SELECT a, 'x' + 0 AS z FROM t ORDER BY 'y' + 0, z, a DESC")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '0'], ['2', '0'], ['1', '0']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame(["Truncated incorrect DOUBLE value: 'x'", "Truncated incorrect DOUBLE value: 'x'", "Truncated incorrect DOUBLE value: 'x'"], array_column($warnings->rows, 2));
    }
}
