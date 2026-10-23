<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication\Binlog;

use MySqlMemory\Command\Replication\Binlog\Stream;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stream::class)]
#[Small]
final class StreamTest extends TestCase
{
    public function testConnectionsKeepIndependentStreams(): void
    {
        $instance = new Instance();
        $first = $instance->connect()->binlog;
        $second = $instance->connect()->binlog;
        self::assertNotSame($first, $second);
        self::assertNull($first->format);
        self::assertSame([], $second->tables);
    }
}
