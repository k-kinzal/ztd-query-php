<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account\Authentication;

use MySqlMemory\Command\Account\Authentication\PasswordAssignments;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordAssignments::class)]
#[Small]
final class PasswordAssignmentsTest extends TestCase
{
    public function testApplyKeepsLaterUpdatesAndEachPasswordFailure(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        try {
            $session->query("SET @a=1, PASSWORD='a', @b=2, PASSWORD FOR nobody@localhost='b', NAMES latin1");
            self::fail('Both invalid passwords must remain SQL conditions.');
        } catch (SqlError $error) {
            self::assertSame(1372, $error->getCode());
            self::assertSame('Password hash should be a 41-digit hexadecimal number', $error->getMessage());
        }
        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Error', '1372', 'Password hash should be a 41-digit hexadecimal number'], ['Error', '1133', "Can't find any matching row in the user table"]], $warnings->rows);
        $result = $session->query('SELECT @a,@b,@@character_set_client')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '2', 'latin1']], $result->rows);
    }
}
