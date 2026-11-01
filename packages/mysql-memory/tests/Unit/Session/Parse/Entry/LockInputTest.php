<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\LockInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(LockInput::class)]
#[Medium]
final class LockInputTest extends TestCase
{
    public function testBeforeResolvesCompletedLocksBeforeALaterMalformedOperand(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $sql = 'LOCK TABLES t READ, d.t LOW_PRIORITY WRITE,?';
        $error = (new LockInput())->before($sql, strlen($sql), $session);

        self::assertSame(1046, $error?->getCode());
        self::assertSame([['Error', 1046, 'No database selected']], $session->diagnostics->conditions);
    }

    public function testReportedKeepsCompletedModeWarningsWhenTheOriginalSyntaxErrorWins(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $syntax = StatementError::ParseError->error('?', 1);
        $error = (new LockInput())->reported($syntax, 'LOCK TABLES d.t LOW_PRIORITY WRITE,?', $session);

        self::assertSame(1064, $error->getCode());
        self::assertTrue($error->recorded);
        self::assertSame([1287, 1064], array_column($session->diagnostics->conditions, 1));
        self::assertSame($syntax, (new LockInput())->reported($syntax, 'SELECT ?', $session));
    }
}
