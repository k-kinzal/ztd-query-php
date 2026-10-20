<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Command\Prepared\PendingUpdate::class)]
#[Small]
final class PendingUpdateTest extends TestCase
{
    public function testParametersRetainsAllMarkersBeforeDeferredValues(): void
    {
        $session = (new \MySqlMemory\Instance('5.6.51', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(a INT)');
        $pending = (new \MySqlMemory\Session\Access\Preparation())->read('UPDATE t JOIN u ON t.a=? SET t.a=?+NOW(9)', $session);
        self::assertInstanceOf(\MySqlMemory\Command\Prepared\PendingUpdate::class, $pending);
        self::assertCount(2, $pending->parameters()->types);
    }

    public function testExecuteKeepsThePreparedDatabaseAndRestoresTheCallerDatabase(): void
    {
        $session = (new \MySqlMemory\Instance('5.6.51', [], ['d', 'other']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(a INT)');
        $pending = (new \MySqlMemory\Session\Access\Preparation())->read('UPDATE t,u SET t.a=NOW(9)', $session);
        self::assertInstanceOf(\MySqlMemory\Command\Prepared\PendingUpdate::class, $pending);
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
        $pending = (new \MySqlMemory\Session\Access\Preparation())->read('UPDATE t,u SET t.a=?+NOW(9) WHERE u.a=?', $session);
        self::assertInstanceOf(\MySqlMemory\Command\Prepared\PendingUpdate::class, $pending);
        $request = $pending->request;
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
        self::assertSame($request, $pending->request);
    }

}
