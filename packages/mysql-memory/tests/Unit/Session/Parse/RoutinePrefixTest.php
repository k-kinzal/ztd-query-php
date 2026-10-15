<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\RoutinePrefix;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoutinePrefix::class)]
#[Small]
final class RoutinePrefixTest extends TestCase
{
    public function testDatabaseChecksTheNameBeforeTheRefusedBody(): void
    {
        $session = (new Instance())->connect();
        $sql = "CREATE PROCEDURE p() SIGNAL SQLSTATE '00000'";

        self::assertSame(1046, (new RoutinePrefix())->database($sql, strlen($sql), $session)?->getCode());
        self::assertNull((new RoutinePrefix())->database($sql, 10, $session));
        self::assertNull((new RoutinePrefix())->database("CREATE PROCEDURE d.p() BEGIN CALL q(); SIGNAL SQLSTATE '00000'; END", 1000, $session));
        self::assertSame(1046, (new RoutinePrefix())->database("CREATE PROCEDURE p() SELECT 'unclosed", 1000, $session)?->getCode());
    }
}
