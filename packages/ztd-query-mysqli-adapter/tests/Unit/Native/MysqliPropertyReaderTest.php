<?php

declare(strict_types=1);

namespace Tests\Unit\Native;

use mysqli;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\MySqlContainer;
use ZtdQuery\Adapter\Mysqli\Native\MysqliPropertyReader;

#[CoversClass(MysqliPropertyReader::class)]
#[Large]
final class MysqliPropertyReaderTest extends TestCase
{
    public function testReadReturnsNativePropertiesAndNullForUnknownNames(): void
    {
        $connection = new mysqli(...MySqlContainer::connectionParameters());
        $reader = new MysqliPropertyReader();
        self::assertSame($connection->affected_rows, $reader->read($connection, 'affected_rows'));
        self::assertSame($connection->client_info, $reader->read($connection, 'client_info'));
        self::assertSame($connection->client_version, $reader->read($connection, 'client_version'));
        self::assertSame($connection->connect_errno, $reader->read($connection, 'connect_errno'));
        self::assertSame($connection->connect_error, $reader->read($connection, 'connect_error'));
        self::assertSame($connection->errno, $reader->read($connection, 'errno'));
        self::assertSame($connection->error, $reader->read($connection, 'error'));
        self::assertSame($connection->error_list, $reader->read($connection, 'error_list'));
        self::assertSame($connection->field_count, $reader->read($connection, 'field_count'));
        self::assertSame($connection->host_info, $reader->read($connection, 'host_info'));
        self::assertSame($connection->info, $reader->read($connection, 'info'));
        self::assertSame($connection->insert_id, $reader->read($connection, 'insert_id'));
        self::assertSame($connection->server_info, $reader->read($connection, 'server_info'));
        self::assertSame($connection->server_version, $reader->read($connection, 'server_version'));
        self::assertSame($connection->sqlstate, $reader->read($connection, 'sqlstate'));
        self::assertSame($connection->protocol_version, $reader->read($connection, 'protocol_version'));
        self::assertSame($connection->thread_id, $reader->read($connection, 'thread_id'));
        self::assertSame($connection->warning_count, $reader->read($connection, 'warning_count'));
        self::assertNull($reader->read($connection, 'unsupported'));
        $connection->close();
    }
}
