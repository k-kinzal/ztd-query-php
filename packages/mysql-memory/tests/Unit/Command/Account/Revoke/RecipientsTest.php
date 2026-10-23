<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account\Revoke;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Revoke\Recipients;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Recipients::class)]
#[Small]
final class RecipientsTest extends TestCase
{
    public function testRunRetainsEveryOccurrenceInDiagnosticOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');

        $reply = $session->run('REVOKE ALL ON d.t FROM u, missing, u IGNORE UNKNOWN USER')[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame(1147, $reply->getCode());
        self::assertSame([
            ['Error', 1147, "There is no such grant defined for user 'u' on host '%' on table 't'"],
            ['Warning', 3162, 'Authorization ID missing does not exist.'],
            ['Error', 1147, "There is no such grant defined for user 'u' on host '%' on table 't'"],
        ], $session->diagnostics->conditions);
    }

    public function testApplyReportsUsageErrorsForEachExistingAccount(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');

        $reply = $session->run('REVOKE RELOAD ON d.* FROM u, missing, u')[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame(1221, $reply->getCode());
        self::assertSame([
            ['Error', 1221, 'Incorrect usage of DB GRANT and GLOBAL PRIVILEGES'],
            ['Error', 1141, "There is no such grant defined for user 'missing' on host '%'"],
            ['Error', 1221, 'Incorrect usage of DB GRANT and GLOBAL PRIVILEGES'],
        ], $session->diagnostics->conditions);
    }

    public function testDynamicRemovesRegisteredPrivilegesAndWarnsForUnregisteredOnes(): void
    {
        $session = (new Instance())->connect();
        $account = new Account(new Identity('u', '%'));
        $account->grants->dynamic['BACKUP_ADMIN'] = false;

        (new Recipients())->dynamic($account, ['BACKUP_ADMIN', 'FOO_BAR'], $session);

        self::assertSame([], $account->grants->dynamic);
        self::assertSame([['Warning', 3929, "Dynamic privilege 'FOO_BAR' is not registered with the server."]], $session->diagnostics->conditions);
    }

    public function testRunKeepsErrorCountsWhenConditionStorageIsBounded(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET max_error_count = 1');

        $reply = $session->run('REVOKE SELECT ON *.* FROM missing, missing, missing')[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame(1141, $reply->getCode());
        self::assertSame([1, 3, 3], [$session->diagnostics->count(), $session->diagnostics->raised(), $session->diagnostics->errors()]);
    }
}
