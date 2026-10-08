<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Server\Processlist;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Processlist::class)]
#[Small]
final class ProcesslistTest extends TestCase
{
    public function testRowsListsTheSessionsConnected(): void
    {
        $instance = new Instance();
        $idle = $instance->connect(database: null);
        $s = $instance->connect();

        $result1 = $s->query('SELECT * FROM performance_schema.processlist')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([[(string) $idle->id, 'root', 'localhost', null, 'Sleep', '0', '', null], [(string) $s->id, 'root', 'localhost', null, 'Query', '0', 'executing', 'SELECT * FROM performance_schema.processlist']], array_map(static fn (array $row): array => array_slice($row, 0, 8), $result1->rows));
    }

    public function testRowsWarnsThatInformationSchemaProcesslistIsDeprecated(): void
    {
        $s = (new Instance())->connect();
        $s->query('SELECT COUNT(*) FROM information_schema.PROCESSLIST');

        $result2 = $s->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['Warning', '1287', "'INFORMATION_SCHEMA.PROCESSLIST' is deprecated and will be removed in a future release. Please use performance_schema.processlist instead"]], $result2->rows);
    }

    public function testRowDescribesASession(): void
    {
        $s = (new Instance())->connect();

        self::assertSame(['ID' => $s->id, 'USER' => 'root', 'HOST' => 'localhost', 'DB' => null, 'COMMAND' => 'Sleep', 'TIME' => 0, 'STATE' => '', 'INFO' => null, 'EXECUTION_ENGINE' => 'PRIMARY'], Processlist::row($s, false, 'init'));
    }
}
