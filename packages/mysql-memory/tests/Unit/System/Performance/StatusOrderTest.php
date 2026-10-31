<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\System\Performance\StatusDefinitions;
use MySqlMemory\System\Performance\StatusOrder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(StatusOrder::class)]
#[Small]
final class StatusOrderTest extends TestCase
{
    public function testOrderedPreservesServerRegistrationOrderForCommandCounters(): void
    {
        $entries = StatusDefinitions::of(GrammarRelease::MySql5651);
        self::assertSame(['Com_assign_to_keycache', 'Com_alter_db'], [$entries[9][0], $entries[10][0]]);
        self::assertCount(count($entries), array_unique(array_column($entries, 0)));
    }
}
