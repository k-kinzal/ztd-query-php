<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account\Grant;

use MySqlMemory\Command\Account\Grant\LegacyGrants;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyGrants::class)]
#[Small]
final class LegacyGrantsTest extends TestCase
{
    public function testExecuteInterleavesRecipientAndPrivilegeConditionsAndReturnsTheFirst(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect();
        $session->query('USE d; CREATE USER u');
        $first = $session->run('GRANT RELOAD ON * TO absent,u')[0];
        self::assertInstanceOf(SqlError::class, $first);
        self::assertSame(1133, $first->getCode());
        self::assertSame([1133, 1221], array_column($session->diagnostics->conditions, 1));
        $second = $session->run('GRANT RELOAD ON * TO u,absent,u')[0];
        self::assertInstanceOf(SqlError::class, $second);
        self::assertSame(1221, $second->getCode());
        self::assertSame([1221, 1133, 1221], array_column($session->diagnostics->conditions, 1));
    }
}
