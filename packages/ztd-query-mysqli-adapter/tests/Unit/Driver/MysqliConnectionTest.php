<?php

declare(strict_types=1);

namespace Tests\Unit;

use mysqli;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Mysqli\MysqliConnection;
use ZtdQuery\Adapter\Mysqli\MysqliResultStatement;
use ZtdQuery\Connection\Exception\DatabaseException;

#[CoversClass(MysqliConnection::class)]
#[UsesClass(MysqliResultStatement::class)]
#[Large]
final class MysqliConnectionTest extends TestCase
{
    public function testQueryReturnsTheNativeRows(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $mysqli = new mysqli($host, 'root', 'root', '', $port);
        $mysqli->set_charset('utf8mb4');
        $database = 'ztd_' . bin2hex(random_bytes(8));
        $mysqli->query('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4');
        $mysqli->select_db($database);
        try {
            $result = (new MysqliConnection($mysqli))->query('SELECT 7 AS id');
            self::assertInstanceOf(MysqliResultStatement::class, $result);
            self::assertSame([['id' => '7']], $result->fetchAll());
            self::assertSame(1, $result->rowCount());
        } finally {
            $mysqli->query('DROP DATABASE `' . $database . '`');
        }
    }

    public function testQueryWrapsAnExecutedWrite(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $mysqli = new mysqli($host, 'root', 'root', '', $port);
        $mysqli->set_charset('utf8mb4');
        $database = 'ztd_' . bin2hex(random_bytes(8));
        $mysqli->query('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4');
        $mysqli->select_db($database);
        try {
            $mysqli->query('CREATE TABLE users (id INT)');
            $result = (new MysqliConnection($mysqli))->query('INSERT INTO users VALUES (1), (2)');
            self::assertInstanceOf(MysqliResultStatement::class, $result);
            self::assertSame([], $result->fetchAll());
            self::assertSame(2, $result->rowCount());
        } finally {
            $mysqli->query('DROP DATABASE `' . $database . '`');
        }
    }

    public function testQueryTranslatesNativeFailureWhenReportingIsDisabled(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $mysqli = new mysqli($host, 'root', 'root', '', $port);
        $mysqli->set_charset('utf8mb4');
        $database = 'ztd_' . bin2hex(random_bytes(8));
        $mysqli->query('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4');
        $mysqli->select_db($database);
        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $this->expectException(DatabaseException::class);
            $this->expectExceptionMessage('doesn\'t exist');
            (new MysqliConnection($mysqli))->query('SELECT * FROM missing');
        } finally {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $mysqli->query('DROP DATABASE `' . $database . '`');
        }
    }
}
