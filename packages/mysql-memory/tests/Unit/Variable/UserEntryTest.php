<?php

declare(strict_types=1);

namespace Tests\Unit\Variable;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Variable\UserEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserEntry::class)]
#[Small]
final class UserEntryTest extends TestCase
{
    public function testFailedSetRestoresTheValueAndItsAssignmentCollationTogether(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('SET NAMES latin1; SET @v=12; SET NAMES utf8mb4');
        $entry = $session->variables->user['v'];
        $error = $session->run("SET @v=13,sql_mode='INVALID_MODE'")[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame($entry, $session->variables->user['v']);
        self::assertSame(12, $entry->value);
        self::assertSame('latin1', $entry->collation->charset->name);
    }
}
