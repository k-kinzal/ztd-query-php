<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use mysqli;
use mysqli_result;
use mysqli_stmt;
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

    public function testStartTransfersHelpDataBeyondCommandLineLimits(): void
    {
        $description = str_repeat('fixture ', 25000);
        $server = Server::start(help: ['topics' => [['1', 'Topic', '1', $description, 'Example']]]);
        try {
            $client = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $result = $client->query('HELP Topic');

            self::assertNotFalse($result);
            self::assertSame([['Topic', $description, 'Example']], $result->fetchAll(PDO::FETCH_NUM));
        } finally {
            $server->stop();
        }
    }

    public function testStartServesPdoQueriesAndServerSidePreparedStatements(): void
    {
        $server = Server::start('8.4.7', ['shop']);
        $pdo = new PDO($server->dsn('shop'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
        $pdo->exec('CREATE TABLE items (id INT PRIMARY KEY, name VARCHAR(20), price DECIMAL(6,2))');
        $inserted = $pdo->exec("INSERT INTO items VALUES (1, 'pen', 1.50), (2, 'ink', 3.25)");
        $select = $pdo->prepare('SELECT id, name, price FROM items WHERE price > ? ORDER BY id');
        $select->execute([1]);
        $selected = $select->fetchAll(PDO::FETCH_ASSOC);
        $insert = $pdo->prepare('INSERT INTO items VALUES (?, ?, ?)');
        $insert->execute([3, 'cap', '0.75']);
        $totals = $pdo->query('SELECT COUNT(*), SUM(price) FROM items');
        self::assertNotFalse($totals);
        $total = $totals->fetch(PDO::FETCH_NUM);
        $server->stop();

        self::assertSame(2, $inserted);
        self::assertSame([['id' => 1, 'name' => 'pen', 'price' => '1.50'], ['id' => 2, 'name' => 'ink', 'price' => '3.25']], $selected);
        self::assertSame(1, $insert->rowCount());
        self::assertSame([3, '5.50'], $total);
    }

    public function testStartAnswersAnErrorThroughPdo(): void
    {
        $server = Server::start('8.4.7', ['shop']);
        $pdo = new PDO($server->dsn('shop'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
        $result = $pdo->query('SELECT * FROM missing');
        $error = $pdo->errorInfo();
        $server->stop();

        self::assertFalse($result);
        self::assertSame(['42S02', 1146, "Table 'shop.missing' doesn't exist"], $error);
    }

    public function testStartPreparesAStoredRevocationConditionThroughPdo(): void
    {
        $server = Server::start('8.4.7', ['shop']);
        try {
            $pdo = new PDO($server->dsn('shop'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, PDO::ATTR_EMULATE_PREPARES => false]);
            $pdo->exec('CREATE TABLE items(id INT)');
            $revoke = $pdo->prepare('REVOKE ALL ON PROCEDURE * FROM u');
            self::assertSame(0, $revoke->columnCount());
            $pdo->beginTransaction();
            $pdo->exec('INSERT INTO items VALUES(7)');
            self::assertFalse($revoke->execute());
            self::assertSame(['42000', 1144, 'Illegal GRANT/REVOKE command; please consult the manual to see which privileges can be used'], $revoke->errorInfo());
            $pdo->exec('ROLLBACK');
            $rows = $pdo->query('SELECT id FROM items');
            self::assertNotFalse($rows);
            self::assertSame([[7]], $rows->fetchAll(PDO::FETCH_NUM));
        } finally {
            $server->stop();
        }
    }

    public function testStartKeepsAColumnlessPreparedInstructionAcrossCatalogChanges(): void
    {
        $server = Server::start('8.4.7', ['shop', 'other']);
        try {
            $pdo = new PDO($server->dsn('shop'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, PDO::ATTR_EMULATE_PREPARES => false]);
            $create = $pdo->prepare('CREATE TABLE IF NOT EXISTS items');
            self::assertSame(0, $create->columnCount());
            self::assertFalse($create->execute());
            self::assertSame(['HY000', 4028, 'A table must have at least one visible column.'], $create->errorInfo());
            $pdo->exec('CREATE TABLE shop.items(id INT)');
            $pdo->exec('INSERT INTO shop.items VALUES(7)');
            $pdo->exec('USE other');
            self::assertTrue($create->execute());
            $rows = $pdo->query('SELECT id FROM shop.items');
            self::assertNotFalse($rows);
            self::assertSame([[7]], $rows->fetchAll(PDO::FETCH_NUM));
            $pdo->exec('DROP TABLE shop.items');
            self::assertFalse($create->execute());
            self::assertSame(['HY000', 4028, 'A table must have at least one visible column.'], $create->errorInfo());
        } finally {
            $server->stop();
        }
    }

    public function testStartServesMysqliQueriesAndPreparedStatements(): void
    {
        $server = Server::start('8.4.7', ['shop']);
        $mysqli = new mysqli($server->host, 'root', '', 'shop', $server->port);
        $mysqli->query('CREATE TABLE items (id INT PRIMARY KEY, name VARCHAR(20), price DECIMAL(6,2))');
        $mysqli->query("INSERT INTO items VALUES (1, 'pen', 1.50), (2, 'ink', 3.25), (3, 'cap', 0.75)");
        $names = $mysqli->query('SELECT name FROM items ORDER BY id DESC');
        self::assertInstanceOf(mysqli_result::class, $names);
        $listed = $names->fetch_all(MYSQLI_NUM);
        $select = $mysqli->prepare('SELECT id, name, price FROM items WHERE name = ?');
        self::assertInstanceOf(mysqli_stmt::class, $select);
        $name = 'ink';
        $select->bind_param('s', $name);
        $select->execute();
        $found = $select->get_result();
        self::assertInstanceOf(mysqli_result::class, $found);
        $selected = $found->fetch_all(MYSQLI_ASSOC);
        $update = $mysqli->prepare('UPDATE items SET price = price + ? WHERE id <= ?');
        self::assertInstanceOf(mysqli_stmt::class, $update);
        $increase = 1.0;
        $last = 2;
        $update->bind_param('di', $increase, $last);
        $update->execute();
        $updated = $update->affected_rows;
        $prices = $mysqli->query('SELECT price FROM items ORDER BY id');
        self::assertInstanceOf(mysqli_result::class, $prices);
        $priced = $prices->fetch_all(MYSQLI_NUM);
        $mysqli->close();
        $server->stop();

        self::assertSame([['cap'], ['ink'], ['pen']], $listed);
        self::assertSame([['id' => 2, 'name' => 'ink', 'price' => '3.25']], $selected);
        self::assertSame(2, $updated);
        self::assertSame([['2.50'], ['4.25'], ['0.75']], $priced);
    }
}
