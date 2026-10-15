<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(Server::class)]
#[Medium]
final class ServerTest extends TestCase
{
    public function testStartRunsAServerThatGreetsClients(): void
    {
        $server = Server::start('8.0.44');
        $socket = stream_socket_client('tcp://' . $server->host . ':' . $server->port, $code, $message, 1.0);
        self::assertIsResource($socket);
        $greeting = fread($socket, 1024);
        fclose($socket);
        $server->stop();

        self::assertSame('127.0.0.1', $server->host);
        self::assertGreaterThan(0, $server->port);
        self::assertIsString($greeting);
        self::assertSame("\x0A8.0.44\x00", substr($greeting, 4, 8));
    }

    public function testStartPreservesNullAndEmptyStartupValues(): void
    {
        $server = Server::start(globals: ['innodb_monitor_reset' => null, 'init_connect' => '']);
        $client = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $result = $client->query('SELECT @@GLOBAL.innodb_monitor_reset, @@GLOBAL.init_connect');
        self::assertNotFalse($result);
        $row = $result->fetch(PDO::FETCH_NUM);
        $server->stop();

        self::assertSame([null, ''], $row);
    }

    public function testDsnNamesTheServerForPdo(): void
    {
        $process = proc_open([PHP_BINARY, '-r', 'sleep(5);'], [], $pipes);
        self::assertIsResource($process);
        $server = new Server($process, '127.0.0.1', 3307);

        self::assertSame('mysql:host=127.0.0.1;port=3307', $server->dsn());
        self::assertSame('mysql:host=127.0.0.1;port=3307;dbname=shop', $server->dsn('shop'));
    }

    public function testStopEndsTheProcess(): void
    {
        $process = proc_open([PHP_BINARY, '-r', 'sleep(5);'], [], $pipes);
        self::assertIsResource($process);
        $server = new Server($process, '127.0.0.1', 3307);
        $server->stop();
        $server->stop();

        self::assertFalse(is_resource($process));
    }
    public function testStartPreservesThePlatformPriorityCapability(): void
    {
        $server = Server::start(resourcePriorities: false);
        try {
            $client = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $client->exec('CREATE RESOURCE GROUP g TYPE USER THREAD_PRIORITY 20');
            $warnings = $client->query('SHOW WARNINGS');
            self::assertNotFalse($warnings);
            self::assertSame([['Warning', 3659, 'Attribute thread_priority is ignored (using default value).']], $warnings->fetchAll(PDO::FETCH_NUM));
        } finally {
            $server->stop();
        }
    }

    public function testStartPreservesThePlatformProcessorDomain(): void
    {
        $server = Server::start(resourceProcessors: 16);
        try {
            $client = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $client->exec('CREATE RESOURCE GROUP g TYPE USER VCPU 9-15');
            $result = $client->query("SELECT VCPU_IDS FROM information_schema.RESOURCE_GROUPS WHERE RESOURCE_GROUP_NAME = 'g'");

            self::assertNotFalse($result);
            self::assertSame('9-15', $result->fetchColumn());
        } finally {
            $server->stop();
        }
    }

}
