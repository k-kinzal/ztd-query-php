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
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(Origins::class)]
#[Small]
final class OriginsTest extends TestCase
{
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
        $statement = $operation->statement;
        $select = $statement instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryStatement ? $statement->query : $statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable::class, $select->from);
        self::assertSame([spl_object_id($select->from->right)], (new Origins((new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary))))->supplied($select->from));
    }
}
