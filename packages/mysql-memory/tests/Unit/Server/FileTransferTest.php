<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use ArrayObject;
use Fiber;
use MySqlMemory\Instance;
use MySqlMemory\Server\Client;
use MySqlMemory\Server\FileTransfer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileTransfer::class)]
#[Small]
final class FileTransferTest extends TestCase
{
    public function testRequestWaitsForTheTerminatorAndConcatenatesFragmentedPackets(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance('5.7.44'), 1, static function (string $packet) use ($sent): void {
            $sent->append($packet);
        });
        $fiber = new Fiber(static fn (): string => $client->files->request('client.csv'));
        $client->instance->transactions->scheduler->admit($fiber);
        $fiber->start();
        self::assertSame(["\x0B\x00\x00\x00\xFBclient.csv"], $sent->getArrayCopy());
        $client->receive("\x03\x00");
        $fiber->resume();
        self::assertTrue($fiber->isSuspended());
        $client->receive("\x00\x01\x01ab\x02\x00\x00\x02cd\x00\x00\x00\x03");
        $fiber->resume();
        self::assertSame("\x01abcd", $fiber->getReturn());
        self::assertFalse($client->files->active);
    }

    public function testReceiveTreatsOnlyAnEmptyPayloadAsTheEndOfTheFile(): void
    {
        $client = new Client(new Instance(), 1, static function (string $packet): void {
        });
        $client->files->receive("\x00");
        self::assertFalse($client->files->complete);
        $client->files->receive('');
        self::assertTrue($client->files->complete);
    }

    public function testWaitingDistinguishesIdleActiveAndCompletedTransfers(): void
    {
        $client = new Client(new Instance(), 1, static function (string $packet): void {
        });
        self::assertFalse($client->files->waiting());
        $client->files->active = true;
        self::assertTrue($client->files->waiting());
        $client->files->receive('');
        self::assertFalse($client->files->waiting());
    }
}
