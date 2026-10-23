<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Performance\VariablesByThread;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(VariablesByThread::class)]
#[Small]
final class VariablesByThreadTest extends TestCase
{
    public function testRowsListsTheSessionValuesOfEachSession(): void
    {
        $instance = new Instance();
        $other = $instance->connect();
        $other->query('SET SESSION sort_buffer_size = 1048576');
        $s = $instance->connect();

        $result1 = $s->query("SELECT THREAD_ID, VARIABLE_VALUE FROM performance_schema.variables_by_thread WHERE VARIABLE_NAME = 'sort_buffer_size'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([[(string) ($other->id + 37), '1048576'], [(string) ($s->id + 37), '262144']], $result1->rows);
        $result2 = $s->query("SELECT * FROM performance_schema.variables_by_thread WHERE VARIABLE_NAME = 'max_connections'")[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([], $result2->rows);
    }
}
