<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\DoCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoCommand::class)]
#[Small]
final class DoCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new DoCommand())->clearsDiagnostics());
    }

    public function testExecuteEvaluatesTheExpressionsAndAnswersNoRows(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query('DO 1, 2')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([0, 0, 0, ''], [$reply->affectedRows, $reply->lastInsertId, $reply->warnings, $reply->info]);
    }

    public function testExecuteKeepsTheWarningsOfEachExpression(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("DO 1 / 0, CAST('x' AS SIGNED)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(2, $reply->warnings);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1365', 'Division by 0'], ['Warning', '1292', "Truncated incorrect INTEGER value: 'x'"]], $warnings->rows);
    }

    public function testExecuteRaisesTheErrorOfAnExpression(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1242);
        $this->expectExceptionMessage('Subquery returns more than 1 row');

        $session->query('DO (SELECT 1 UNION SELECT 2)');
    }

    public function testExecuteLeavesOneFoundRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (2), (3)');
        $session->query('SELECT SQL_CALC_FOUND_ROWS * FROM t LIMIT 1');
        $session->query('DO 1, 2');
        $result = $session->query('SELECT FOUND_ROWS()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }
}
