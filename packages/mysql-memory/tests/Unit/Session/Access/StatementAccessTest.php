<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\StatementAccess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatementAccess::class)]
#[Small]
final class StatementAccessTest extends TestCase
{
    public function testCheckRejectsQueriesWhileTableCreationWaitsForCommit(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT) START TRANSACTION');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3977);
        $session->query('SELECT 1');
    }
}
