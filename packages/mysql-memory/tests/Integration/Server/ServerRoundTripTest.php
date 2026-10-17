<?php

declare(strict_types=1);

namespace Tests\Integration\Server;

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
final class ServerRoundTripTest extends TestCase
{
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
