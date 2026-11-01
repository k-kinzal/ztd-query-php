<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication\Binlog;

use MySqlMemory\Command\Replication\Binlog\Events;
use MySqlMemory\Command\Replication\Binlog\Format;
use MySqlMemory\Command\Replication\Binlog\TableMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableMap::class)]
#[Small]
final class TableMapTest extends TestCase
{
    public function testReadRetainsSourceIdentityAndStorageWidths(): void
    {
        $events = iterator_to_array((new Events())->read('PZ/YahMBAAAASQAAAH0BAAAAAFUAAAAAAAEADmJpbmxvZ19maXh0dXJlAAlyb3dzX3Rlc3QAAgMPAlAAAgEBAAID/P8Ab3RIMj2f2GoeAQAAADoAAAC3AQAAAABVAAAAAAABAAIAAv8AAQAAAAVmaXJzdAACAAAABnNlY29uZEHUAMo='));
        $map = TableMap::read($events[0], new Format(19, str_repeat("\0", 18) . "\x08", 4));
        self::assertSame(['binlog_fixture', 'rows_test', [3, 15], ['', "\x50\0"]], [$map->schema, $map->table, $map->types, $map->metadata]);
    }
}
