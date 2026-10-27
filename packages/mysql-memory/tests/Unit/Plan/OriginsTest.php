<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Origins;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(Origins::class)]
#[Small]
final class OriginsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function providerRollupOrigins(): iterable
    {
        yield 'MySQL 5.6 scan' => ['5.6.51', '', 't', 3];
        yield 'MySQL 5.7 scan' => ['5.7.44', '', 't', 3];
        yield 'MySQL 5.6 key' => ['5.6.51', ' WHERE n=1', '', 0];
        yield 'MySQL 5.7 key' => ['5.7.44', ' WHERE n=1', 't', 3];
    }

    #[DataProvider('providerRollupOrigins')]
    public function testRollupPreservesLegacyBaseFlagsExceptMySql56ConstantKeys(string $version, string $where, string $table, int $flags): void
    {
        $session = (new Instance($version))->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT PRIMARY KEY)');
        $result = $session->query('SELECT n FROM t' . $where . ' GROUP BY n WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertSame([$table, $flags], [$result->columns[0]->table, $result->columns[0]->flags & 3]);
    }

    public function testStoredAggregateFlagsBlobFieldsAfterStorage(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query("CREATE TABLE t(n INT,s TEXT); INSERT INTO t VALUES(1,'a'),(2,'b')");
        $group = $session->query('SELECT MIN(s),CONCAT(MIN(s)) FROM t GROUP BY n')[0];
        $distinct = $session->query('SELECT DISTINCT CONCAT(MIN(s)) FROM t GROUP BY n')[0];
        $rollup = $session->query('SELECT MIN(s) FROM t GROUP BY n WITH ROLLUP')[0];
        self::assertInstanceOf(ResultSet::class, $group);
        self::assertInstanceOf(ResultSet::class, $distinct);
        self::assertInstanceOf(ResultSet::class, $rollup);

        self::assertSame([16, 0, 16, 0], [$group->columns[0]->flags & 16, $group->columns[1]->flags & 16, $distinct->columns[0]->flags & 16, $rollup->columns[0]->flags & 16]);
    }

    public function testResolvedKeepsBaseMetadataThroughASelectedAlias(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(id INT PRIMARY KEY)');
        $result = $session->query('SELECT id AS x, (SELECT x) AS y FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame(['y', 'id', 't', 't', 'd'], [$result->columns[1]->name, $result->columns[1]->originalName, $result->columns[1]->table, $result->columns[1]->originalTable, $result->columns[1]->schema]);
        self::assertSame(3, $result->columns[1]->flags & 3);
    }

    public function testDerivedKeepsMySql57PreparedStarOriginsBeforeDerivedAliasing(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT,b INT)');
        $star = $session->execute('SELECT * FROM (SELECT * FROM t) AS q', prepared: true);
        $named = $session->execute('SELECT q.a FROM (SELECT * FROM t) AS q', prepared: true);
        $text = $session->execute('SELECT * FROM (SELECT * FROM t) AS q');
        $renamed = $session->execute('SELECT * FROM (SELECT a AS x,b AS y FROM t) AS q', prepared: true);
        self::assertInstanceOf(ResultSet::class, $star);
        self::assertInstanceOf(ResultSet::class, $named);
        self::assertInstanceOf(ResultSet::class, $text);
        self::assertInstanceOf(ResultSet::class, $renamed);
        self::assertSame(['t', 'q', 'q'], [$star->columns[0]->table, $named->columns[0]->table, $text->columns[0]->table]);
        self::assertSame(['x', 't'], [$renamed->columns[0]->originalName, $renamed->columns[0]->table]);
    }

    public function testGroupedDerivedCopiesModernMergedOriginsWithoutTheirDatabase(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT,b INT)');
        $grouped = $session->query('SELECT * FROM (SELECT * FROM t) q GROUP BY 1,2')[0];
        $materialized = $session->query('SELECT * FROM (SELECT * FROM t LIMIT 3) q GROUP BY 1,2')[0];
        $distinct = $session->query('WITH c AS (SELECT * FROM t) SELECT DISTINCT * FROM c')[0];
        self::assertInstanceOf(ResultSet::class, $grouped);
        self::assertInstanceOf(ResultSet::class, $materialized);
        self::assertInstanceOf(ResultSet::class, $distinct);
        self::assertSame(['', 'd', ''], [$grouped->columns[0]->schema, $materialized->columns[0]->schema, $distinct->columns[0]->schema]);
    }

    public function testGroupedDerivedKeepsMetadataWhenConditionsMakeTheResultEmpty(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT,b INT); CREATE VIEW v AS SELECT a AS x,b AS y FROM t');
        $grouped = $session->query('SELECT * FROM (SELECT * FROM t) q WHERE FALSE GROUP BY 1,2')[0];
        $limited = $session->query('SELECT * FROM (SELECT * FROM t) q GROUP BY 1,2 LIMIT 0')[0];
        $view = $session->query('SELECT DISTINCT * FROM v WHERE FALSE')[0];
        self::assertInstanceOf(ResultSet::class, $grouped);
        self::assertInstanceOf(ResultSet::class, $limited);
        self::assertInstanceOf(ResultSet::class, $view);
        self::assertSame(['d', 'd', 'v'], [$grouped->columns[0]->schema, $limited->columns[0]->schema, $view->columns[0]->originalTable]);
    }

    public function testGroupedDerivedKeepsViewNamesAlongsideTheUnderlyingTable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT,b INT); CREATE VIEW v AS SELECT a AS x,b AS y FROM t');
        $result = $session->query('SELECT DISTINCT * FROM v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['x', 'x', 'v', 't', 'd'], [$result->columns[0]->name, $result->columns[0]->originalName, $result->columns[0]->table, $result->columns[0]->originalTable, $result->columns[0]->schema]);
    }

    public function testResolvedPreservesTheWrittenSystemTableName(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT DEFAULT_ENCRYPTION FROM information_schema.schemata')[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame('schemata', $result->columns[0]->table);
    }

    public function testOriginKeepsTheColumnOfAReducedScalarSubquery(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(id INT PRIMARY KEY)');
        $result = $session->query('SELECT (SELECT (SELECT x.id LIMIT 0)) AS scalar_id FROM t AS x')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['scalar_id', 'id', 'x', 't', 'd'], [$result->columns[0]->name, $result->columns[0]->originalName, $result->columns[0]->table, $result->columns[0]->originalTable, $result->columns[0]->schema]);
        self::assertSame(3, $result->columns[0]->flags & 3);
    }

    public function testOriginReportsTheColumnDefaultReads(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY, a INT DEFAULT 5)');
        $result = $session->query('SELECT DEFAULT(x.a), DEFAULT(id), DEFAULT(a) + 0 FROM t AS x')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['a', 'x', 't', 'd'], [$result->columns[0]->originalName, $result->columns[0]->table, $result->columns[0]->originalTable, $result->columns[0]->schema]);
        self::assertSame([1, ''], [$result->columns[1]->flags & 1, $result->columns[2]->table]);
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

    public function testOriginKeepsSourceMetadataThroughGroupingParentheses(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(id INT PRIMARY KEY)');
        $result = $session->query('SELECT ((x.id)) AS v FROM t x')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['v', 'id', 'x', 't', 'd'], [$result->columns[0]->name, $result->columns[0]->originalName, $result->columns[0]->table, $result->columns[0]->originalTable, $result->columns[0]->schema]);
        self::assertSame(3, $result->columns[0]->flags & 3);
    }

    public function testOriginKeepsWrappedDefaultsDistinctFromColumnValues(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a INT DEFAULT 7); INSERT INTO t VALUES (99)');
        $result = $session->query('SELECT DEFAULT(a),(DEFAULT(a)),+DEFAULT(a),{fn DEFAULT(a)},a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7', '7', '7', '7', '99']], $result->rows);
        self::assertSame(['t', 't', 't', 't', 't'], array_column($result->columns, 'table'));
        self::assertSame(['a', 'a', 'a', 'a', 'a'], array_column($result->columns, 'originalName'));
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

    public function testAggregateOrderDropsKeysOnlyWhenSortingGroupsByAnOwnedAggregate(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(id INT PRIMARY KEY, a INT); INSERT INTO t VALUES (1,10),(2,5)');
        $aggregate = $session->query('SELECT id, SUM(a) AS s FROM t GROUP BY id ORDER BY s')[0];
        $column = $session->query('SELECT id FROM t GROUP BY id ORDER BY a')[0];
        self::assertInstanceOf(ResultSet::class, $aggregate);
        self::assertInstanceOf(ResultSet::class, $column);

        self::assertSame([0, 2], [$aggregate->columns[0]->flags & 2, $column->columns[0]->flags & 2]);
        self::assertSame(['t', 'id'], [$aggregate->columns[0]->originalTable, $aggregate->columns[0]->originalName]);
    }

    public function testOriginsNameStoredWindowOutputsAndTheirParents(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a INT); INSERT INTO t VALUES(1),(2)');
        $stored = $session->query('SELECT NTILE(2) OVER() AS n, (LEAD(a) OVER())+0 AS v FROM t')[0];
        $empty = $session->query('SELECT NTILE(2) OVER() AS n, (LEAD(a) OVER())+0 AS v FROM t WHERE FALSE')[0];
        self::assertInstanceOf(ResultSet::class, $stored);
        self::assertInstanceOf(ResultSet::class, $empty);

        self::assertSame(['n', 'v'], array_column($stored->columns, 'originalName'));
        self::assertSame([32801, 32768], array_column($stored->columns, 'flags'));
        self::assertSame(['', ''], array_column($empty->columns, 'originalName'));
        self::assertSame([32929, 32896], array_column($empty->columns, 'flags'));
    }

    public function testOriginsDropTheKeyFlagsOfABufferedResult(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT PRIMARY KEY)');
        $buffered = $session->query('SELECT SQL_BUFFER_RESULT a FROM t')[0];
        $direct = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $buffered);
        self::assertInstanceOf(ResultSet::class, $direct);
        self::assertSame([0, 2, 't', 't'], [$buffered->columns[0]->flags & 2, $direct->columns[0]->flags & 2, $buffered->columns[0]->originalTable, $direct->columns[0]->originalTable]);
    }

    public function testJoinedOrderDropsKeysWhenSortingIndependentRows(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(id INT PRIMARY KEY, v INT); INSERT INTO t VALUES (1,1),(2,1)');
        $buffered = $session->query('SELECT a.id,b.id FROM t a JOIN t b ON a.v=b.v ORDER BY a.id,b.id')[0];
        $dependent = $session->query('SELECT a.id,b.id FROM t a JOIN t b ON a.id=b.id ORDER BY a.id,b.id')[0];
        $empty = $session->query('SELECT a.id,b.id FROM t a JOIN t b ON a.v=b.v ORDER BY a.id,b.id LIMIT 0')[0];
        self::assertInstanceOf(ResultSet::class, $buffered);
        self::assertInstanceOf(ResultSet::class, $dependent);
        self::assertInstanceOf(ResultSet::class, $empty);

        self::assertSame([0, 0, 2, 2], [$buffered->columns[0]->flags & 2, $buffered->columns[1]->flags & 2, $dependent->columns[0]->flags & 2, $empty->columns[0]->flags & 2]);
        self::assertSame(['a', 't', 'id'], [$buffered->columns[0]->table, $buffered->columns[0]->originalTable, $buffered->columns[0]->originalName]);
    }

    public function testOriginsKeepsTheTableOfARolledUpColumnInMySql57(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $result = $session->query('SELECT a FROM t GROUP BY a WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame('t', $result->columns[0]->table);
    }

    public function testKeptFlagsANotNullColumnOfAnAggregatedBlockNotNullInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT NOT NULL, a INT)');
        $session->query('CREATE TABLE u (id INT NOT NULL)');
        $result = $session->query('SELECT t.id, t.a, u.id, COUNT(*) FROM t LEFT JOIN u ON t.id = u.id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([1, 0, 0], [$result->columns[0]->flags & 1, $result->columns[1]->flags & 1, $result->columns[2]->flags & 1]);
    }

    public function testSuppliedAnswersTheRelationsAnOuterJoinSuppliesNullFor(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT)');
        $operation = $session->analyze('SELECT * FROM t AS x LEFT JOIN t AS y ON 1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        $statement = $operation->query;
        $select = $statement instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryStatement ? $statement->query : $statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable::class, $select->from);
        self::assertSame([spl_object_id($select->from->right)], (new Origins((new Planner($statement, $operation->statementInformation(), $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary))))->supplied($select->from));
    }
    public function testStoredAssignmentNamesTheCopiedAggregateFieldOnMySql80(): void
    {
        $session = (new Instance('8.0.44'))->connect();
        $session->query("USE mysql;CREATE TABLE t(n INT,d DATE);INSERT INTO t VALUES(1,'2001-01-02'),(2,'2001-01-03')");
        $result = $session->query('SELECT DISTINCT @x:=MIN(d),ROW_NUMBER() OVER () FROM t GROUP BY 1+1 WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame('<temporary>', $result->columns[0]->table);
        self::assertSame([['2001-01-02', '1'], ['2001-01-02', '2']], $result->rows);
    }

    public function testRollupDropsMySql57BaseFlagsOnlyWhenTheConstantKeyExists(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT PRIMARY KEY);INSERT INTO t VALUES(1),(2)');
        $found = $session->query('SELECT n FROM t WHERE n=1 GROUP BY n WITH ROLLUP')[0];
        $missing = $session->query('SELECT n FROM t WHERE n=3 GROUP BY n WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $found);
        self::assertInstanceOf(ResultSet::class, $missing);
        self::assertSame(['', 0], [$found->columns[0]->table, $found->columns[0]->flags & 3]);
        self::assertSame(['t', 3], [$missing->columns[0]->table, $missing->columns[0]->flags & 3]);
    }

    public function testDefaultDistinguishesMergedSourcesFromBufferedAliases(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT DEFAULT 7)');
        $merged = $session->query('SELECT DEFAULT(x) FROM (SELECT a AS x FROM t) q')[0];
        $buffered = $session->query('SELECT DEFAULT(x) FROM (SELECT a AS x FROM t LIMIT 1) q')[0];
        self::assertInstanceOf(ResultSet::class, $merged);
        self::assertInstanceOf(ResultSet::class, $buffered);
        self::assertSame(['a', 't', 't'], [$merged->columns[0]->originalName, $merged->columns[0]->table, $merged->columns[0]->originalTable]);
        self::assertSame(['x', 'q', 't'], [$buffered->columns[0]->originalName, $buffered->columns[0]->table, $buffered->columns[0]->originalTable]);
    }

}
