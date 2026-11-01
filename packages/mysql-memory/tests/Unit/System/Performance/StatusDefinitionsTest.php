<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\System\Performance\StatusDefinitions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(StatusDefinitions::class)]
#[Small]
final class StatusDefinitionsTest extends TestCase
{
    public function testOfDeclaresScopesAndReleaseSpecificCounters(): void
    {
        $legacy = array_column(StatusDefinitions::of(GrammarRelease::MySql5651), null, 0);
        $current = array_column(StatusDefinitions::of(GrammarRelease::MySql847), null, 0);
        self::assertSame(['Aborted_clients', 'Global', '0', true], $current['Aborted_clients']);
        self::assertSame('OFF', $current['Compression'][2]);
        self::assertSame('0.000000', $legacy['Last_query_cost'][2]);
        self::assertSame('0.000000', $current['Last_query_cost'][2]);
        self::assertSame('ON', $current['Innodb_redo_log_enabled'][2]);
        self::assertArrayNotHasKey('Innodb_redo_log_enabled', $legacy);
    }
}
