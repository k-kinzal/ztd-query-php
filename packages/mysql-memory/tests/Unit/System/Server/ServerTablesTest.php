<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\System\Server\ServerTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(ServerTables::class)]
#[Small]
final class ServerTablesTest extends TestCase
{
    public function testOfReadsTheCatalogOfARelease(): void
    {
        self::assertSame(ServerTables::of(GrammarRelease::MySql847), ServerTables::of(GrammarRelease::MySql847));
        self::assertSame('InnoDB', ServerTables::of(GrammarRelease::MySql5744)->engines[0][0]);
        self::assertContains('InnoDB', array_column(ServerTables::of(GrammarRelease::MySql910)->engines, 0));
    }
}
