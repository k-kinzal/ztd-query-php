<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use Container\Endpoint;
use Container\MySql80Container;
use Container\MySql84Container;
use mysqli;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliConnection;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultStatement;
use ZtdQuery\Connection\Exception\DatabaseException;

#[CoversClass(MysqliConnection::class)]
#[UsesClass(MysqliResultStatement::class)]
#[Large]
final class MysqliConnectionTest extends TestCase
{
    public function testQueryReturnsTheNativeRows(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $endpoint = $container->getData(Endpoint::class);
        try {
            $mysqli = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, $endpoint->database, $endpoint->port);
            $mysqli->set_charset('utf8mb4');
            $result = (new MysqliConnection($mysqli))->query('SELECT 7 AS id');
            self::assertInstanceOf(MysqliResultStatement::class, $result);
            self::assertSame([['id' => '7']], $result->fetchAll());
            self::assertSame(1, $result->rowCount());
        } finally {
            $container->stop();
        }
    }

    public function testQueryWrapsAnExecutedWrite(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $endpoint = $container->getData(Endpoint::class);
        try {
            $mysqli = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, $endpoint->database, $endpoint->port);
            $mysqli->set_charset('utf8mb4');
            $mysqli->query('CREATE TABLE users (id INT)');
            $result = (new MysqliConnection($mysqli))->query('INSERT INTO users VALUES (1), (2)');
            self::assertInstanceOf(MysqliResultStatement::class, $result);
            self::assertSame([], $result->fetchAll());
            self::assertSame(2, $result->rowCount());
        } finally {
            $container->stop();
        }
    }

    public function testQueryTranslatesNativeFailureWhenReportingIsDisabled(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        $endpoint = $container->getData(Endpoint::class);
        try {
            $mysqli = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, $endpoint->database, $endpoint->port);
            $mysqli->set_charset('utf8mb4');
            mysqli_report(MYSQLI_REPORT_OFF);
            try {
                $this->expectException(DatabaseException::class);
                $this->expectExceptionMessage('doesn\'t exist');
                (new MysqliConnection($mysqli))->query('SELECT * FROM missing');
            } finally {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            }
        } finally {
            $container->stop();
        }
    }
}
