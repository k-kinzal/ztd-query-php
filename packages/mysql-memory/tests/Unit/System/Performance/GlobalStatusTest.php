<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Performance\GlobalStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(GlobalStatus::class)]
#[Small]
final class GlobalStatusTest extends TestCase
{
    public function testRowsListsTheStatusVariablesWithoutTheStatementCounters(): void
    {
        $s = (new Instance())->connect();

        $result1 = $s->query("SELECT * FROM performance_schema.global_status WHERE VARIABLE_NAME IN ('Innodb_page_size', 'Threads_connected', 'Com_select')")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['Innodb_page_size', '16384'], ['Threads_connected', '1']], $result1->rows);
    }
}
