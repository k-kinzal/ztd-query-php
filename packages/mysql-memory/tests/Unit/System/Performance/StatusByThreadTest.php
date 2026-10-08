<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Performance\StatusByThread;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatusByThread::class)]
#[Small]
final class StatusByThreadTest extends TestCase
{
    public function testRowsListsTheSessionStatusOfEachSession(): void
    {
        $s = (new Instance())->connect();

        $result1 = $s->query("SELECT * FROM performance_schema.status_by_thread WHERE VARIABLE_NAME IN ('Bytes_received', 'Uptime')")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([[(string) ($s->id + 37), 'Bytes_received', '0']], $result1->rows);
    }
}
