<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Command\Prepared\PreparedStatement::class)]
#[Small]
final class PreparedUpdateBoundaryTest extends TestCase
{
    public function testParametersRetainsAllMarkersBeforeDeferredValues(): void
    {
        $session = (new \MySqlMemory\Instance('5.6.51', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(a INT)');
        $operation = (new \MySqlMemory\Session\Access\Preparation())->read('UPDATE t JOIN u ON t.a=? SET t.a=?+NOW(9)', $session);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        (new \MySqlMemory\Session\Access\Preparation())->check($operation, $session);
        $pending = \MySqlMemory\Command\Prepared\PreparedStatement::capture($operation, $session);
        self::assertCount(2, \MySqlMemory\Command\Prepared\ParameterBindings::capture($operation)->types);
    }

    public function testExecuteKeepsThePreparedDatabaseAndRestoresTheCallerDatabase(): void
    {
        $session = (new \MySqlMemory\Instance('5.6.51', [], ['d', 'other']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(a INT)');
        $operation = (new \MySqlMemory\Session\Access\Preparation())->read('UPDATE t,u SET t.a=NOW(9)', $session);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        (new \MySqlMemory\Session\Access\Preparation())->check($operation, $session);
        $pending = \MySqlMemory\Command\Prepared\PreparedStatement::capture($operation, $session);
        $session->variables->database = 'other';
        $this->expectExceptionMessage("Too big precision 9 specified for column 'now'. Maximum is 6.");
        try {
            $pending->execute($session, 'opaque request label', []);
        } finally {
            self::assertSame('other', $session->variables->database);
            self::assertSame('opaque request label', $session->text);
        }
    }

    public function testRunReachesTheSameObjectAfterItsInputTablesChange(): void
    {
        $session = (new \MySqlMemory\Instance('5.6.51', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(a INT)');
        $operation = (new \MySqlMemory\Session\Access\Preparation())->read('UPDATE t,u SET t.a=?+NOW(9) WHERE u.a=?', $session);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        (new \MySqlMemory\Session\Access\Preparation())->check($operation, $session);
        $pending = \MySqlMemory\Command\Prepared\PreparedStatement::capture($operation, $session);
        $request = $pending->statement;
        $parameters = [[1, \MySqlMemory\Typing\Domain::integer()], [2, \MySqlMemory\Typing\Domain::integer()]];
        $session->query('DROP TABLE u');
        $missing = $pending->run($session, 'unrelated label', $parameters);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing[0]);
        self::assertSame(1146, $missing[0]->getCode());
        $session->query('CREATE TABLE u(a INT); INSERT INTO t VALUES(1); INSERT INTO u VALUES(2)');
        $reached = $pending->run($session, 'unrelated label', $parameters);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reached[0]);
        self::assertSame(1426, $reached[0]->getCode());
        self::assertSame([1426, 1105], array_column($session->diagnostics->conditions, 1));
        self::assertSame($request, $pending->statement);
    }

    public function testRefusedPreservesLegacyPreparationAndExecutionConditions(): void
    {
        $session = (new \MySqlMemory\Instance('5.6.51', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(a INT)');
        $session->query("PREPARE st FROM 'UPDATE t,u SET t.a=NOW(9)'");
        self::assertSame([], $session->diagnostics->conditions);
        $result = $session->run('EXECUTE st');
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $result[0]);
        self::assertSame(1426, $result[0]->getCode());
        self::assertSame([1426, 1105], array_column($session->diagnostics->conditions, 1));
    }

    public function testRefusedChecksTheCompleteJoinBeforeItsPendingAssignments(): void
    {
        $session = (new \MySqlMemory\Instance('5.6.51', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(a INT)');
        $result = $session->run("PREPARE st FROM 'UPDATE t JOIN u ON missing SET t.a=NOW(9)'");
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $result[0]);
        self::assertSame("Unknown column 'missing' in 'on clause'", $result[0]->getMessage());
        self::assertSame([1054], array_column($session->diagnostics->conditions, 1));
    }

}
