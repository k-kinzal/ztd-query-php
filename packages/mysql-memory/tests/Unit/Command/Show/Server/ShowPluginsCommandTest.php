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
}
