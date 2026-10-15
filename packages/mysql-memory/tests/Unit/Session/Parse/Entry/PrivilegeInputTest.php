<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\PrivilegeInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrivilegeInput::class)]
#[Small]
final class PrivilegeInputTest extends TestCase
{
    public function testDatabasePrecedesAnInvalidPrivilegeAndRecipient(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $session->query("GRANT r@h ON PROCEDURE * TO u@'bad@host'");
    }
}
