<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Server\Plugins;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Plugins::class)]
#[Small]
final class PluginsTest extends TestCase
{
    public function testRowsListsThePluginsOfTheRelease(): void
    {
        $s = (new Instance())->connect();

        $result1 = $s->query("SELECT PLUGIN_NAME, PLUGIN_VERSION, PLUGIN_STATUS, PLUGIN_TYPE, PLUGIN_TYPE_VERSION, PLUGIN_LIBRARY, PLUGIN_LIBRARY_VERSION, PLUGIN_AUTHOR, LOAD_OPTION FROM information_schema.PLUGINS WHERE PLUGIN_NAME = 'binlog'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['binlog', '1.0', 'ACTIVE', 'STORAGE ENGINE', '80407.0', null, null, 'Oracle Corporation', 'FORCE']], $result1->rows);
    }
}
