<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\RefreshFlags;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Server\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefreshFlags::class)]
#[Small]
final class RefreshFlagsTest extends TestCase
{
    public function testApplyCombinesCacheLogAndStatusFlags(): void
    {
        $client = new Client(new Instance('8.0.44'), 7, static function (string $bytes): void {
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $registry = $client->instance->registry;
        $client->instance->dictionary->cache->open('d', 't');
        $registry->status->add('Com_do', $client->id);
        (new RefreshFlags($client->session()))->apply(2 | 4 | 16);

        self::assertSame([1, 2], $registry->binaryLog->files);
        self::assertSame(1, $registry->status->read('Flush_commands'));
        self::assertSame(0, $registry->status->read('Com_do', $client->id));
        (new RefreshFlags($client->session()))->reset(128 | 64);
        self::assertSame([1], $registry->binaryLog->files);
    }

    public function testResetRecordsBothLegacyResourceFailures(): void
    {
        $client = new Client(new Instance('5.6.51'), 7, static function (string $bytes): void {
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1186);
        (new RefreshFlags($client->session()))->reset(128 | 64);
    }

}
