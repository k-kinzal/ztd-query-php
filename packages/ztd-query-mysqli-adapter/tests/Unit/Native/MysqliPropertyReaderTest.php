<?php

declare(strict_types=1);

namespace Tests\Unit\Native;

use Container\Endpoint;
use Container\MySql80Container;
use Container\MySql84Container;
use mysqli;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;
use ZtdQuery\Adapter\Mysqli\Native\MysqliPropertyReader;

#[CoversClass(MysqliPropertyReader::class)]
#[Large]
final class MysqliPropertyReaderTest extends TestCase
{
    public function testReadReturnsNativePropertiesAndNullForUnknownNames(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $endpoint = $container->getData(Endpoint::class);
        try {
            $connection = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, $endpoint->database, $endpoint->port);
            $connection->set_charset('utf8mb4');
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
        } finally {
            $container->stop();
        }
    }

    public function testReadPreservesFailedConnectionDetails(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $endpoint = $container->getData(Endpoint::class);
        try {
            mysqli_report(MYSQLI_REPORT_OFF);
            try {
                $connection = new mysqli($endpoint->host, 'missing_' . bin2hex(random_bytes(8)), 'invalid', $endpoint->database, $endpoint->port);
                $reader = new MysqliPropertyReader();
                $error = $connection->connect_error;
                self::assertIsString($error);
                self::assertNotSame('', $error);
                self::assertGreaterThan(0, $connection->connect_errno);
                self::assertSame($connection->connect_errno, $reader->read($connection, 'connect_errno'));
                self::assertSame($error, $reader->read($connection, 'connect_error'));
            } finally {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            }
        } finally {
            $container->stop();
        }
    }

    public function testReadPreservesInformationFromMultipleWrites(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $endpoint = $container->getData(Endpoint::class);
        try {
            $connection = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, $endpoint->database, $endpoint->port);
            $connection->set_charset('utf8mb4');
            $connection->query('CREATE TEMPORARY TABLE info_rows (id INT PRIMARY KEY)');
            $connection->query('INSERT INTO info_rows VALUES (1), (2)');
            $reader = new MysqliPropertyReader();
            $info = $connection->info;
            self::assertIsString($info);
            self::assertNotSame('', $info);
            self::assertSame($info, $reader->read($connection, 'info'));
            self::assertSame(2, $reader->read($connection, 'affected_rows'));
            $connection->close();
        } finally {
            $container->stop();
        }
    }

}
