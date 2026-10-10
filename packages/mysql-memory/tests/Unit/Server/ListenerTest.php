<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use MySqlMemory\Instance;
use MySqlMemory\Server\Listener;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Listener::class)]
#[Medium]
final class ListenerTest extends TestCase
{
    public function testReadDispatchesAPacketAndClosesTheDisconnectedPeer(): void
    {
        $instance = new Instance();
        $listener = new Listener($instance, 'tcp://127.0.0.1:0');
        $client = stream_socket_client($listener->open());
        self::assertIsResource($client);
        $id = $listener->accept();
        self::assertIsInt($id);
        fread($client, 1024);
        fwrite($client, "\x26\x00\x00\x01\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $listener->read($id);
        $reply = fread($client, 1024);
        $peer = stream_socket_get_name($client, false);
        self::assertIsString($peer);
        $session = $instance->sessions[1]->get();
        self::assertNotNull($session);
        self::assertSame('localhost:' . substr($peer, (int) strrpos($peer, ':') + 1), \MySqlMemory\System\Server\Processlist::row($session, false, '')['HOST']);
        fclose($client);
        $listener->read($id);
        $listener->read($id);

        self::assertSame("\x07\x00\x00\x02\x00\x00\x00\x02\x00\x00\x00", $reply);
        self::assertSame([], $instance->registry->threads->connected);
    }

    public function testCloseReleasedClosesAnIdleConnectionKilledByAnotherConnection(): void
    {
        $server = Server::start();
        $first = new mysqli($server->host, 'root', '', '', $server->port);
        $second = new mysqli($server->host, 'root', '', '', $server->port);
        $second->query('KILL CONNECTION ' . $first->thread_id);
        usleep(20000);

        $this->expectException(mysqli_sql_exception::class);
        $this->expectExceptionCode(2006);

        $first->query('SELECT 1');
    }

    public function testServeInterruptsALockWaitWithoutEndingTheTransactionForKillQuery(): void
    {
        $server = Server::start();
        $holder = new mysqli($server->host, 'root', '', '', $server->port);
        $waiter = new mysqli($server->host, 'root', '', '', $server->port);
        $holder->query('CREATE DATABASE d');
        $holder->query('CREATE TABLE d.t (id INT PRIMARY KEY, v INT)');
        $holder->query('INSERT INTO d.t VALUES (1, 10), (2, 20)');
        $holder->query('BEGIN');
        $holder->query('UPDATE d.t SET v = 11 WHERE id = 1');
        $waiter->query('BEGIN');
        $waiter->query('UPDATE d.t SET v = 21 WHERE id = 2');
        $waiter->query('UPDATE d.t SET v = 12 WHERE id = 1', MYSQLI_ASYNC);
        usleep(100000);
        $holder->query('KILL QUERY ' . $waiter->thread_id);
        mysqli_report(MYSQLI_REPORT_OFF);
        $completed = $waiter->reap_async_query();
        $error = $waiter->errno;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $waiter->query('COMMIT');
        $holder->query('ROLLBACK');
        $rows = $holder->query('SELECT v FROM d.t ORDER BY id');
        self::assertInstanceOf(mysqli_result::class, $rows);

        self::assertSame([false, 1317, [['10'], ['21']]], [$completed, $error, $rows->fetch_all()]);
    }

    public function testOpenListensOnAFreeLocalPort(): void
    {
        $listener = new Listener(new Instance(), 'tcp://127.0.0.1:0');

        self::assertMatchesRegularExpression('/\Atcp:\/\/127\.0\.0\.1:[1-9][0-9]*\z/', $listener->open());
    }

    public function testOpenAnswersTheAddressOfAUnixSocket(): void
    {
        $path = sys_get_temp_dir() . '/mysql-memory-' . bin2hex(random_bytes(6)) . '.sock';
        $listener = new Listener(new Instance(), 'unix://' . $path);
        $address = $listener->open();
        $created = file_exists($path);
        unlink($path);

        self::assertSame('unix://' . $path, $address);
        self::assertTrue($created);
    }

    public function testOpenRefusesAnAddressItCannotListenOn(): void
    {
        $listener = new Listener(new Instance(), 'nosuch://127.0.0.1:0');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot listen on nosuch://127.0.0.1:0: ');

        $listener->open();
    }

    public function testServeRefusesAListenerThatIsNotOpen(): void
    {
        $listener = new Listener(new Instance(), 'tcp://127.0.0.1:0');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The listener is not open.');

        $listener->serve();
    }
    public function testServeLetsAStatementWaitForARowLockWhileTheOtherConnectionsAreServed(): void
    {
        $server = Server::start();
        $holder = new mysqli($server->host, 'root', '', '', $server->port);
        $waiter = new mysqli($server->host, 'root', '', '', $server->port);
        $holder->query('CREATE DATABASE d');
        $holder->query('CREATE TABLE d.t (id INT PRIMARY KEY, v INT)');
        $holder->query('INSERT INTO d.t VALUES (1, 10)');
        $holder->query('BEGIN');
        $holder->query('UPDATE d.t SET v = 11 WHERE id = 1');
        $waiter->query('UPDATE d.t SET v = v + 1 WHERE id = 1', MYSQLI_ASYNC);
        usleep(100000);
        $holder->query('COMMIT');
        $waited = $waiter->reap_async_query();
        $result = $holder->query('SELECT v FROM d.t');
        self::assertInstanceOf(mysqli_result::class, $result);
        $rows = $result->fetch_all();
        $server->stop();

        self::assertSame([true, 1, [['12']]], [$waited, $waiter->affected_rows, $rows]);
    }

    public function testServeRefusesTheRequestThatClosesADeadlockBetweenEqualTransactions(): void
    {
        $server = Server::start();
        $first = new mysqli($server->host, 'root', '', '', $server->port);
        $second = new mysqli($server->host, 'root', '', '', $server->port);
        $first->query('CREATE DATABASE d');
        $first->query('CREATE TABLE d.t (id INT PRIMARY KEY, v INT)');
        $first->query('INSERT INTO d.t VALUES (1, 10), (2, 20)');
        $first->query('BEGIN');
        $first->query('UPDATE d.t SET v = 11 WHERE id = 1');
        $second->query('BEGIN');
        $second->query('SELECT * FROM d.t WHERE id = 2 FOR UPDATE');
        $first->query('SELECT * FROM d.t WHERE id = 2 FOR UPDATE', MYSQLI_ASYNC);
        usleep(100000);

        $this->expectException(mysqli_sql_exception::class);
        $this->expectExceptionCode(1213);
        $this->expectExceptionMessage('Deadlock found when trying to get lock; try restarting transaction');

        $second->query('SELECT * FROM d.t WHERE id = 1 FOR UPDATE');
    }

    public function testAcceptGreetsAConnectionAndAnswersItsId(): void
    {
        $listener = new Listener(new Instance(), 'tcp://127.0.0.1:0');
        $client = stream_socket_client($listener->open());
        self::assertIsResource($client);
        $id = $listener->accept();
        $greeting = fread($client, 1024);

        self::assertIsInt($id);
        self::assertIsString($greeting);
        self::assertSame("\x0A8.4.7\x00", substr($greeting, 4, 7));
    }

    public function testRunForgetsTheFiberOfWorkThatEnded(): void
    {
        $instance = new Instance();
        $listener = new Listener($instance, 'tcp://127.0.0.1:0');
        $listener->run(1, static fn (): bool => true);
        $listener->run(1);

        self::assertSame([], $instance->transactions->scheduler->fibers);
    }

    public function testCloseEndsTheSessionOfAConnection(): void
    {
        $instance = new Instance();
        $listener = new Listener($instance, 'tcp://127.0.0.1:0');
        $client = stream_socket_client($listener->open());
        self::assertIsResource($client);
        $id = $listener->accept();
        self::assertIsInt($id);
        fread($client, 1024);
        $listener->close($id);

        self::assertSame('', fread($client, 1024));
    }
}
