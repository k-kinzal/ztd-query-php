<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Performance\SessionVariables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionVariables::class)]
#[Small]
final class SessionVariablesTest extends TestCase
{
    public function testRowsListsTheSessionValues(): void
    {
        $s = (new Instance())->connect();
        $s->query("SET SESSION sql_mode = 'ANSI_QUOTES'");

        $result1 = $s->query("SELECT * FROM performance_schema.session_variables WHERE VARIABLE_NAME = 'sql_mode'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['sql_mode', 'ANSI_QUOTES']], $result1->rows);
    }
}
