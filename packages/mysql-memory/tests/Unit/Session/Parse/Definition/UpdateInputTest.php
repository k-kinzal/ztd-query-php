<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Session\Parse\Definition\UpdateInput::class)]
#[Small]
final class UpdateInputTest extends TestCase
{
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
