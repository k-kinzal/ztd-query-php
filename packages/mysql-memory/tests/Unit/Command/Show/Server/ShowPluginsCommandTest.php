<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show\Server;

use MySqlMemory\Command\Show\Server\ShowPluginsCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowPluginsCommand::class)]
#[Small]
final class ShowPluginsCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowPluginsCommand())->clearsDiagnostics());
    }

    public function testExecuteListsThePlugins(): void
    {
        $result = (new Instance())->connect()->query('SHOW PLUGINS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertCount(48, $result->rows);
        self::assertContains(['ndbcluster', 'DISABLED', 'STORAGE ENGINE', null, 'GPL'], $result->rows);
        self::assertSame(['PLUGIN_NAME', 'PLUGINS'], [$result->columns[0]->originalName, $result->columns[0]->table]);
    }

    public function testExecuteListsThePluginsOfTheRelease(): void
    {
        $result = (new Instance('9.1.0'))->connect()->query('SHOW PLUGINS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertNotContains('mysql_native_password', array_column($result->rows, 0));
    }
}
