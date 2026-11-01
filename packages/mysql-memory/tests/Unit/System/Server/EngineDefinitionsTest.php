<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\System\Server\EngineDefinitions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(EngineDefinitions::class)]
#[Small]
final class EngineDefinitionsTest extends TestCase
{
    public function testOfPreservesReleaseOrderingAndStorageCapabilities(): void
    {
        $legacy = EngineDefinitions::of(GrammarRelease::MySql5744);
        $current = array_column(EngineDefinitions::of(GrammarRelease::MySql847), null, 0);
        self::assertSame('InnoDB', $legacy[0][0]);
        self::assertSame(['DEFAULT', 'YES', 'YES', 'YES'], [$current['InnoDB'][1], $current['InnoDB'][3], $current['InnoDB'][4], $current['InnoDB'][5]]);
        self::assertSame('NO', $current['FEDERATED'][1]);
    }
}
