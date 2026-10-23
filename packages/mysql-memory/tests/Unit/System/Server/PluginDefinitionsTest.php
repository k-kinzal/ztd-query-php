<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\System\Server\PluginDefinitions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(PluginDefinitions::class)]
#[Small]
final class PluginDefinitionsTest extends TestCase
{
    public function testOfAppliesAuthenticationAndInterfaceVersionChanges(): void
    {
        $old = array_column(PluginDefinitions::of(GrammarRelease::MySql8044), null, 0);
        $current = array_column(PluginDefinitions::of(GrammarRelease::MySql847), null, 0);
        $latest = array_column(PluginDefinitions::of(GrammarRelease::MySql910), null, 0);
        self::assertSame('ACTIVE', $old['mysql_native_password'][2]);
        self::assertSame('DISABLED', $current['mysql_native_password'][2]);
        self::assertArrayNotHasKey('mysql_native_password', $latest);
        self::assertSame(['8.4', '80407.0'], [$current['InnoDB'][1], $current['InnoDB'][4]]);
    }
}
