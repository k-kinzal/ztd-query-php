<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Performance\SessionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionStatus::class)]
#[Small]
final class SessionStatusTest extends TestCase
{
    public function testRowsListsTheSessionStatus(): void
    {
        $s = (new Instance())->connect();

        $result1 = $s->query("SELECT * FROM performance_schema.session_status WHERE VARIABLE_NAME IN ('Bytes_received', 'Compression')")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['Bytes_received', '0'], ['Compression', 'OFF']], $result1->rows);
    }
}
