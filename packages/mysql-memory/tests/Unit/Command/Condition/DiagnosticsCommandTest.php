<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Condition;

use MySqlMemory\Command\Condition\DiagnosticsCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiagnosticsCommand::class)]
#[Small]
final class DiagnosticsCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersFalse(): void
    {
        self::assertFalse((new DiagnosticsCommand())->clearsDiagnostics());
    }

    public function testExecuteReadsTheNumberOfConditionsAndTheRowCount(): void
    {
        $session = (new Instance())->connect();

        $session->query("SELECT 1/0, CAST('x' AS SIGNED)");
        $session->query('GET DIAGNOSTICS @n = NUMBER, @r = ROW_COUNT');

        $result1 = $session->query('SELECT @n, @r')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['2', '-1']], $result1->rows);
    }

    public function testExecuteReadsTheItemsOfAnErrorTheServerRaised(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $session->run('SELECT * FROM nope');
        $session->query('GET DIAGNOSTICS CONDITION 1 @m = MESSAGE_TEXT, @e = MYSQL_ERRNO, @s = RETURNED_SQLSTATE, @c = CLASS_ORIGIN, @t = TABLE_NAME');

        $result2 = $session->query('SELECT @m, @e, @s, @c, @t')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([["Table 'd.nope' doesn't exist", '1146', '42S02', 'ISO 9075', '']], $result2->rows);
    }

    public function testExecuteReadsTheItemsASignalSet(): void
    {
        $session = (new Instance())->connect();

        $session->query("SIGNAL SQLSTATE '01000' SET TABLE_NAME = 'tt'");
        $session->query('GET DIAGNOSTICS CONDITION 1 @c = CLASS_ORIGIN, @s = RETURNED_SQLSTATE, @e = MYSQL_ERRNO, @t = TABLE_NAME');

        $result3 = $session->query('SELECT @c, @s, @e, @t')[0];
        self::assertInstanceOf(ResultSet::class, $result3);
        self::assertSame([['', '01000', '1642', 'tt']], $result3->rows);
    }

    public function testExecuteAddsAnErrorForAConditionNumberOutOfRange(): void
    {
        $session = (new Instance())->connect();

        $session->query('SELECT 1/0');
        $reply = $session->query('GET DIAGNOSTICS CONDITION 5 @m = MESSAGE_TEXT')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([['Warning', 1365, 'Division by 0'], ['Error', 1758, 'Invalid condition number']], $session->diagnostics->conditions);
    }

    public function testExecuteRefusesTheStackedArea(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(3004);
        $this->expectExceptionMessage('GET STACKED DIAGNOSTICS when handler not active');

        $session->query('GET STACKED DIAGNOSTICS @n = NUMBER');
    }

    public function testPositionRoundsTheNumberAndRefusesOneOutOfRange(): void
    {
        $session = (new Instance())->connect();
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $command = new DiagnosticsCommand();

        self::assertSame([1, null, null], [$command->position('1.6', Domain::decimal(2, 1), $context, 2), $command->position(3, Domain::integer(), $context, 2), $command->position(null, Domain::integer(), $context, 2)]);
    }
}
