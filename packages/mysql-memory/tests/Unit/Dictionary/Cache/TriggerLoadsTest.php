<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary\Cache;

use MySqlMemory\Dictionary\Cache\TriggerLoads;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TriggerLoads::class)]
#[Small]
final class TriggerLoadsTest extends TestCase
{
    public function testLoadTracksConnectionsAndChangedDefinitionLists(): void
    {
        $first = (new Instance())->connect();
        $second = $first->instance->connect();
        $cache = new TriggerLoads();
        self::assertTrue($cache->load($first, 'd', 't', []));
        self::assertFalse($cache->load($first, 'd', 't', []));
        self::assertTrue($cache->load($second, 'd', 't', []));
        $first->query('CREATE DATABASE d; CREATE TABLE d.t(a INT); CREATE TRIGGER d.tr BEFORE INSERT ON d.t FOR EACH ROW SET NEW.a=1');
        $definitions = $first->instance->dictionary->schema('d')->triggers ?? [];
        self::assertCount(1, $definitions);
        self::assertTrue($cache->load($first, 'd', 't', $definitions));
        self::assertFalse($cache->load($first, 'd', 't', $definitions));
        self::assertTrue($cache->load($first, 'd', 't', []));
    }

    public function testCloseEvictsOnlyTheRequestedHandlesAcrossConnections(): void
    {
        $first = (new Instance())->connect();
        $second = $first->instance->connect();
        $cache = new TriggerLoads();
        $cache->load($first, 'd', 't', []);
        $cache->load($second, 'd', 't', []);
        $cache->load($first, 'other', 't', []);
        $cache->close('d', 't');
        self::assertTrue($cache->load($first, 'd', 't', []));
        self::assertTrue($cache->load($second, 'd', 't', []));
        self::assertFalse($cache->load($first, 'other', 't', []));
        $cache->close('other');
        self::assertTrue($cache->load($first, 'other', 't', []));
        $cache->close();
        self::assertTrue($cache->load($first, 'd', 't', []));
    }
}
