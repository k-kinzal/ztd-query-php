<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication\Binlog;

use MySqlMemory\Command\Replication\Binlog\Events;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Events::class)]
#[Small]
final class EventsTest extends TestCase
{
    public function testReadYieldsEachBoundedEventBeforeReadingTheNext(): void
    {
        $event = pack('VCVVVv', 0, 2, 1, 19, 0, 0);
        self::assertSame([$event, $event], iterator_to_array((new Events())->read(base64_encode($event . $event))));
    }

    public function testReadRefusesInvalidBase64(): void
    {
        $this->expectExceptionCode(1575);
        iterator_to_array((new Events())->read('a'));
    }

    public function testReadRefusesAnIncompleteEvent(): void
    {
        $this->expectExceptionCode(1149);
        iterator_to_array((new Events())->read(base64_encode(pack('VCVVVv', 0, 2, 1, 20, 0, 0))));
    }
}
