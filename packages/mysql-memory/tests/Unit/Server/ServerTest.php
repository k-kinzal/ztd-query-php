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
}
