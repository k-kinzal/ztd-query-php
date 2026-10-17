<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Performance\PersistedVariables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PersistedVariables::class)]
#[Small]
final class PersistedVariablesTest extends TestCase
{
    public function testRowsListsOnlyTheSavedConfiguration(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET PERSIST_ONLY autocommit=0; SET GLOBAL autocommit=1');
        $read = $session->query('SELECT VARIABLE_NAME, VARIABLE_VALUE FROM performance_schema.persisted_variables')[0];

        self::assertInstanceOf(ResultSet::class, $read);
        self::assertSame([['autocommit', 'OFF']], $read->rows);
    }
}
