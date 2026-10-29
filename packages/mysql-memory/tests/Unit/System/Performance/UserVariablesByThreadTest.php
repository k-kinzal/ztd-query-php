<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Performance\UserVariablesByThread;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserVariablesByThread::class)]
#[Small]
final class UserVariablesByThreadTest extends TestCase
{
    public function testRowsRetainNamesNullsBinaryBytesAndNumericRepresentations(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @MixedCase=1, @n=NULL, @b=_binary 0xff, @d=1.234, @f=1.25e2, @u=18446744073709551615; SET @MIXEDCASE=2; SELECT @UnsetVariable');
        $result = $session->query('SELECT VARIABLE_NAME, HEX(VARIABLE_VALUE) FROM performance_schema.user_variables_by_thread ORDER BY VARIABLE_NAME')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['b', 'FF'], ['d', '312E323334'], ['f', '313235'], ['MixedCase', '32'], ['n', null], ['u', '3138343436373434303733373039353531363135']], $result->rows);
    }

    public function testRowsTrackAssignmentsInOtherSessionsAndRemoveClosedSessions(): void
    {
        $instance = new Instance();
        $other = $instance->connect();
        $session = $instance->connect();
        $other->query("SET @Name='first'");
        $session->query("SET @Name='second'");
        $result = $session->query('SELECT THREAD_ID, VARIABLE_NAME, VARIABLE_VALUE FROM performance_schema.user_variables_by_thread ORDER BY THREAD_ID')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[(string) ($other->id + 37), 'Name', 'first'], [(string) ($session->id + 37), 'Name', 'second']], $result->rows);
        $other->close();
        $result = $session->query('SELECT VARIABLE_VALUE FROM performance_schema.user_variables_by_thread')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['second']], $result->rows);
    }
}
