<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Transform;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Builder;
use MySqlMemory\Iterator\Transform\LockIterator;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(LockIterator::class)]
#[Small]
final class LockIteratorTest extends TestCase
{
    public function testInitStartsTheInputForTheTransactionOfTheSession(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (2); BEGIN');
        $operation = $session->analyze('SELECT * FROM t WHERE a = 2 FOR UPDATE');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $root = (new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary))->query($statement, null)->root;
        self::assertInstanceOf(Project::class, $root);
        $iterator = (new Builder())->build($root->input);
        self::assertInstanceOf(LockIterator::class, $iterator);
        $iterator->init(new Frame($context));

        self::assertSame([[2], null], [$iterator->read(), $iterator->read()]);
    }

    public function testReadSkipsTheRowsAnotherTransactionHoldsWithSkipLocked(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $first = $instance->connect('root', 'localhost', 'd');
        $second = $instance->connect('root', 'localhost', 'd');
        $first->query('CREATE TABLE t (a INT PRIMARY KEY); INSERT INTO t VALUES (1), (2), (3); BEGIN; SELECT * FROM t WHERE a = 2 FOR UPDATE');
        $result = $second->query('SELECT * FROM t FOR UPDATE SKIP LOCKED')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['3']], $result->rows);
    }

    public function testReadRefusesARowAnotherTransactionHoldsWithNowait(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $first = $instance->connect('root', 'localhost', 'd');
        $second = $instance->connect('root', 'localhost', 'd');
        $first->query('CREATE TABLE t (a INT PRIMARY KEY); INSERT INTO t VALUES (1), (2); BEGIN; UPDATE t SET a = 20 WHERE a = 2');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3572);
        $this->expectExceptionMessage('Statement aborted because lock(s) could not be acquired immediately and NOWAIT is set.');

        $second->query('SELECT * FROM t WHERE a = 2 FOR SHARE NOWAIT');
    }

    public function testReadLeavesAloneARowAnotherTransactionHoldsThatMeetsTheConditionInNoVersion(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $first = $instance->connect('root', 'localhost', 'd');
        $second = $instance->connect('root', 'localhost', 'd');
        $first->query('CREATE TABLE t (a INT PRIMARY KEY, b INT); INSERT INTO t VALUES (1, 1), (2, 2); BEGIN; UPDATE t SET b = 3 WHERE a = 2');
        $result = $second->query('SELECT * FROM t WHERE a = 1 FOR UPDATE NOWAIT')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1']], $result->rows);
    }

    public function testHoldsEvaluatesThePreconditionOnce(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); INSERT INTO t VALUES (1)');
        $result = $session->query('SELECT * FROM t WHERE 1 = 0 FOR UPDATE')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
    }

    public function testMeetsEvaluatesTheConditionOfTheBlock(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT * FROM t WHERE a > 1 FOR UPDATE');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $root = (new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary))->query($statement, null)->root;
        self::assertInstanceOf(Project::class, $root);
        $iterator = (new Builder())->build($root->input);
        self::assertInstanceOf(LockIterator::class, $iterator);
        $iterator->init(new Frame($context));

        self::assertSame([true, false], [$iterator->meets([2]), $iterator->meets([1])]);
    }

    public function testCommittedMeetsTriesTheCommittedVersionOfARowAnotherTransactionChanged(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $first = $instance->connect('root', 'localhost', 'd');
        $second = $instance->connect('root', 'localhost', 'd');
        $first->query('CREATE TABLE t (a INT PRIMARY KEY, b INT); INSERT INTO t VALUES (1, 1); BEGIN; UPDATE t SET b = 5');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1205);

        $second->query('SELECT * FROM t WHERE b = 1 FOR UPDATE');
    }

    public function testLatestPutsTheLatestVersionOfTheLockedRowsInARow(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); INSERT INTO t VALUES (7)');
        $operation = $session->analyze('SELECT * FROM t FOR UPDATE');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $root = (new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary))->query($statement, null)->root;
        self::assertInstanceOf(Project::class, $root);
        $iterator = (new Builder())->build($root->input);
        self::assertInstanceOf(LockIterator::class, $iterator);
        $iterator->init(new Frame($context));
        $iterator->read();

        self::assertSame([7], $iterator->latest([0]));
    }

    public function testSplicedPutsAVersionOfARowInPlace(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); CREATE TABLE u (b INT, c INT); INSERT INTO t VALUES (1); INSERT INTO u VALUES (2, 3)');
        $operation = $session->analyze('SELECT * FROM t, u FOR UPDATE');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $root = (new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary))->query($statement, null)->root;
        self::assertInstanceOf(Project::class, $root);
        $builder = new Builder();
        $iterator = $builder->build($root->input);
        self::assertInstanceOf(LockIterator::class, $iterator);
        $iterator->init(new Frame($context));
        $scan = array_values($builder->scans)[1];

        self::assertSame([1, 8, 9], $iterator->spliced([1, 2, 3], $scan, 1, [8, 9]));
    }
}
