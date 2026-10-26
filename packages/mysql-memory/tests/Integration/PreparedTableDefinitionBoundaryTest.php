<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Command\Prepared\ParameterBindings;
use MySqlMemory\Command\Prepared\PreparedStatement;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\Preparation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(PreparedStatement::class)]
#[Medium]
final class PreparedTableDefinitionBoundaryTest extends TestCase
{
    public function testCompletePartitionRequestUsesTheOrdinaryPreparedBoundary(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = (new Preparation())->read('CREATE TABLE t(a INT) PARTITION BY HASH(ROW(1,2))', $session);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        (new Preparation())->check($operation, $session);
        self::assertSame([], ParameterBindings::capture($operation)->types);
        $prepared = PreparedStatement::capture($operation, $session);

        $reply = $prepared->run($session, 'opaque partition request', [])[0];

        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply);
        self::assertSame(1564, $reply->getCode());
        self::assertSame('opaque partition request', $session->text);
        self::assertSame([['Error', 1564, 'This partition function is not allowed']], $session->diagnostics->conditions);
    }

    public function testRunReusesTheSameHeaderAcrossADatabaseTransition(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode=''");
        $operation = (new Preparation())->read('CREATE TABLE later.t(a INT) ENGINE=missing PARTITION BY HASH(ROW(1,2))', $session);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        (new Preparation())->check($operation, $session);
        $definition = PreparedStatement::capture($operation, $session);
        self::assertSame([['Warning', 1286, "Unknown storage engine 'missing'"], ['Warning', 1266, "Using storage engine InnoDB for table 't'"]], $session->diagnostics->conditions);
        $header = $definition->statement;
        $before = $definition->run($session, 'request label, not SQL', []);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $before[0]);
        self::assertSame(1049, $before[0]->getCode());
        $session->query('CREATE DATABASE later');
        $after = $definition->run($session, 'another request label', []);

        self::assertSame($header, $definition->statement);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $after[0]);
        self::assertSame(1564, $after[0]->getCode());
        self::assertSame([['Error', 1564, 'This partition function is not allowed']], $session->diagnostics->conditions);
    }

    public function testExecutePreservesThePreparationDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $operation = (new Preparation())->read('CREATE TABLE t(a INT) PARTITION BY HASH(ROW(1,2))', $session);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        (new Preparation())->check($operation, $session);
        $definition = PreparedStatement::capture($operation, $session);
        $session->query('DROP DATABASE d');
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'd'");

        $definition->execute($session, 'opaque label', []);
    }
    public function testParametersRetainsTheQueryBindings(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = (new Preparation())->read('CREATE TABLE t UNION=() SELECT ? AS v', $session);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        (new Preparation())->check($operation, $session);
        self::assertCount(1, ParameterBindings::capture($operation)->types);
    }
    public function testExecuteKeepsTheSameImplicitKeyInstructionAndItsPreparationDatabase(): void
    {
        $session = (new Instance('8.4.7', [], ['d', 'other']))->connect(database: 'd');
        $operation = (new Preparation())->read('CREATE TABLE IF NOT EXISTS t(a INT) PARTITION BY KEY()', $session);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        (new Preparation())->check($operation, $session);
        $prepared = PreparedStatement::capture($operation, $session);
        $header = $prepared->statement;
        $session->query('USE other; CREATE TABLE t(a INT); INSERT INTO t VALUES(9)');
        $missing = $prepared->run($session, 'not SQL', []);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing[0]);
        self::assertSame(1488, $missing[0]->getCode());
        $session->query('CREATE TABLE d.t(a INT); INSERT INTO d.t VALUES(7)');
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $prepared->run($session, 'still not SQL', [])[0]);
        self::assertSame($header, $prepared->statement);
        self::assertSame('other', $session->variables->database);
        $rows = $session->query('SELECT * FROM d.t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $rows);
        self::assertSame([['7']], $rows->rows);
        $session->query('DROP TABLE d.t');
        $again = $prepared->run($session, 'unrelated request', []);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $again[0]);
        self::assertSame(1488, $again[0]->getCode());
        self::assertSame('other', $session->variables->database);
    }

}
