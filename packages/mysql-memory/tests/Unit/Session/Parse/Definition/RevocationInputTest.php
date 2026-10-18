<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Session\Parse\Definition\RevocationInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(RevocationInput::class)]
#[Medium]
final class RevocationInputTest extends TestCase
{
    public function testRefusedRetainsThePreparationAndInvocationBoundary(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query("PREPARE st FROM 'REVOKE ALL ON PROCEDURE * FROM u'");
        self::assertSame([], $session->diagnostics->conditions);
        $reply = $session->run('EXECUTE st');
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame(1144, $reply[0]->getCode());
        self::assertSame([['Error', 1144, 'Illegal GRANT/REVOKE command; please consult the manual to see which privileges can be used']], $session->diagnostics->conditions);
    }

    public function testRefusedRequiresTheCurrentDatabaseBeforePreparingAWildcard(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $reply = $session->run("PREPARE st FROM 'REVOKE ALL ON PROCEDURE * FROM u'");
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame(1046, $reply[0]->getCode());
        $missing = $session->run('EXECUTE st');
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing[0]);
        self::assertSame(1243, $missing[0]->getCode());
    }
}
